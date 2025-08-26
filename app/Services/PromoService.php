<?php

namespace App\Services;

use App\Models\PromoCampaign;
use App\Models\PromoItem;
use App\Services\WbServiceRep;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use App\Jobs\Promo\SnapshotItemsJob;
use App\Jobs\Promo\ApplyTempDiscountJob;
use App\Jobs\Promo\RevertDiscountJob;
use Illuminate\Support\Facades\Log;

class PromoService
{
    public function __construct(private WbServiceRep $wb) {}

    public function start(array $dto): array
    {
        $campaign = DB::transaction(function () use ($dto) {
            $externalId = $dto['externalId'] ?? Str::ulid()->toBase32();

            $campaign = PromoCampaign::query()
                ->where('external_id', $externalId)
                ->first();

            if (!$campaign) {
                $campaign = PromoCampaign::create([
                    'external_id' => $externalId,
                    'status'      => 'queued',
                ]);
            } else {
                $campaign->update([
                    'status' => 'queued',
                ]);
            }

            $now = now();
            $items = collect($dto['products'])
                ->map(fn ($p) => [
                    'campaign_id'        => $campaign->id,
                    'article'            => trim((string)$p['article']),
                    'temp_discount'      => (int)$p['discount'],
                    'snapshot_discount'  => null,
                    'snapshot_captured_at' => null,
                    'status'             => 'pending',
                    'error'              => null
                ])
                ->all();

            PromoItem::upsert(
                $items,
                ['campaign_id','article'],
                ['temp_discount','updated_at','status','error']
            );

            return $campaign->fresh();
        });

        $wbToken = $dto['wbToken'];
        $goods   = $this->wb->fetchAllGoodsDetailed($wbToken);

        $discountsMap = collect($goods)
            ->mapWithKeys(fn ($g) => [$g['nmId'] => $g['discount']])
            ->all();

        $items = PromoItem::query()
            ->where('campaign_id', $campaign->id)
            ->get();

        foreach ($items as $item) {
            $nm = collect($goods)->firstWhere('nmId', $item->article);

            if ($nm) {
                $item->update([
                    'snapshot_discount'    => $nm['discount'],
                    'snapshot_captured_at' => now(),
                ]);
            }
        }

        $payload = $items->map(fn ($i) => [
            'nmId'    => (int)$i->article,
            'discount'=> $i->temp_discount,
        ])->values()->all();

        $res = $this->wb->setDiscounts($dto['wbToken'], $payload);

        DB::transaction(function () use ($campaign, $res) {
            $now = now();

            if (!empty($res['error']) || empty($res['id'])) {
                PromoItem::where('campaign_id', $campaign->id)->update([
                    'status'     => 'error',
                    'error'      => $res['errorText'] ?? 'WB task create failed',
                    'updated_at' => $now,
                ]);

                $campaign->update([
                    'status'     => 'failed',
                    'updated_at' => $now,
                ]);
            } else {
                PromoItem::where('campaign_id', $campaign->id)->update([
                    'status'     => 'done',
                    'updated_at' => $now,
                ]);

                $campaign->update([
                    'status'     => 'applied',
                    'updated_at' => $now,
                ]);
            }
        });

        return [
            'campaign'     => $campaign->toArray(),
            'items'        => $campaign->items()->get()->toArray(),
            'wbTask'       => [
                'id'            => $res['id'] ?? null,
                'alreadyExists' => (bool)($res['alreadyExists'] ?? false),
            ],
        ];

    }

    public function revert(int $campaignId, string $wbToken): array
    {
        $campaign = PromoCampaign::findOrFail($campaignId);

        $items = PromoItem::where('campaign_id', $campaign->id)->get();
        $payload = $items->map(fn($i) => [
            'nmId'     => (int)$i->article,
            'discount' => (int)($i->snapshot_discount ?? 0),
        ])->values()->all();

        $res = $this->wb->setDiscounts($wbToken, $payload);

        DB::transaction(function () use ($campaign, $res) {
            $now = now();

            if (!empty($res['error']) || empty($res['id'])) {
                PromoItem::where('campaign_id', $campaign->id)->update([
                    'status'     => 'error',
                    'error'      => $res['errorText'] ?? 'WB task create failed',
                    'updated_at' => $now,
                ]);

                $campaign->update([
                    'status'     => 'failed',
                    'updated_at' => $now,
                ]);
            } else {
                PromoItem::where('campaign_id', $campaign->id)->update([
                    'status'     => 'done',
                    'updated_at' => $now,
                ]);

                $campaign->update([
                    'status'     => 'reverted',
                    'updated_at' => $now,
                ]);
            }
        });

        return [
            'campaign' => $campaign->toArray(),
            'items'    => $campaign->items()->get()->toArray(),
            'wbTask'   => [
                'id'            => $res['id'] ?? null,
                'alreadyExists' => (bool)($res['alreadyExists'] ?? false),
            ],
        ];
    }

    public function status(int $campaignId): array
    {
        $c = PromoCampaign::findOrFail($campaignId);
        $totals = [
            'planned'   => (int)$c->totals_planned,
            'applied'   => (int)$c->totals_applied,
            'reverted'  => (int)$c->totals_reverted,
            'errors'    => (int)$c->totals_errors,
        ];

        return [
            'campaignId' => $c->id,
            'status'     => $c->status,
            'date'       => $c->date->toDateString(),
            'from'       => (string)$c->from_time,
            'to'         => (string)$c->to_time,
            'totals'     => $totals,
            'updatedAt'  => $c->updated_at?->toDateTimeString(),
        ];
    }
}
