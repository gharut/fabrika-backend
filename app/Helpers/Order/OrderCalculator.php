<?php

namespace App\Helpers\Order;

use App\Constants\Services;
use App\Enums\ServiceSteps;
use App\Helpers\Helper;
use App\Models\Consumable;
use App\Models\Order;
use App\Models\OrderService;
use App\Models\Product;
use App\Models\Service;
use Nette\Utils\Helpers;

class OrderCalculator
{
    private Order $order;
    private $deliveryLimits = null;
    private $result = [
        "pickup" => [
            'deliveryItems' => [
                'qty' => 0,
                'total_weight' => 0,
                'total_volume' => 0,
                'volume_price' => 0,
                'weight_price' => 0,
                'total_price' => 0,
            ],
            'services' => [],
            'total_price' => 0
        ],
        "packaging" => [
            "products" => [],
            "total_price" => 0,
        ],
        "total_price" => 0
    ];

    /**
     * @var $services Service[]
     */
    private $services = [];

    function __construct(Order $order)
    {
        $this->order = $order;
        $this->deliveryLimits = Helper::getItemLimits();
//        $this->services = Service::with('attributes')->all()->keyBy('id');
        $this->services = Service::with('attributes')->get()->keyBy('id');

        $this->result['pickup']=$this->calculatePickup();
        $this->result['total_price']=$this->result['pickup']['total_price'];

        $this->result['packaging']=$this->calculatePackaging();
        $this->result['total_price']=$this->result['packaging']['total_price'];


    }

    public function getResult(): array {
        return $this->result;
    }

    public function calculatePickup()
    {
        $result = [
            'deliveryItems' => [],
            'services' => [],
            'total_price' => 0
        ];

        foreach ($this->order->order_services as $service) {
            if ($service->service_id == Services::DELIVERY_SERVICE_ID) {
                $supply_items = $this->getDeliveryItems();
                $result['deliveryItems'] = $supply_items;
                $result['total_price'] += $supply_items['total_price'];
                //continue;
            }

            if($this->services[$service->service_id]->step == ServiceSteps::PREPROCESSING->value) {

                $serviceCalculation = $this->getCalculationService($service);
                $result['services'][] = $serviceCalculation;
                $result['total_price'] += $serviceCalculation['total_price'];
            }

        }

        return $result;
    }

    private function calculatePackaging() {
        $result = [
            "products" => [],
            "total_price" => 0
        ];

        foreach ($this->order->products as $product) {
            if($product->parent_id != null) {
                continue;
            }

            $product_result = [
                "product_name" => $product->name,
                "product_count" => $product->totalCount(),
                "colors_count" => $product->children()->count(),
                "services" => [],
                "total_price" => 0
            ];

            foreach ($this->order->order_services as $service) {
                if($service->product_id == $product->id){
                    $product_service = $this->getCalculationService($service);
                    $product_result['services'][] = $product_service;
                    $product_result['total_price'] += $product_service['total_price'];
                }
            }

            $result['products'][] = $product_result;
            $result['total_price'] += $product_result['total_price'];

        }

        return $result;
    }

    private function getCalculationService(OrderService $orderService) {

        $service = $this->services[$orderService->service_id];
        $result = [
            'service_name' => $service->name,
            'service_price' => $service->price,
            'count' => 0,
            'total_price' => 0,
        ];

        $product_total_qty = 0;
        $complect = 0;
        if($orderService->product_id) {
            $product_total_qty = $orderService->product->totalCount();
            $complect = $orderService->product->complect;
        }

        $service_attributes = $service->attributes()->first();
        if($service_attributes) {
            $apply_type = $service_attributes->apply_to_price_type;
            $attr_price = 0;
            $os_attribute_data = $orderService->service_attribute;
            $os_json_attribute_data = json_decode($os_attribute_data, true);
            if(json_last_error() == JSON_ERROR_NONE){
                $attr_price = $os_json_attribute_data['price'];
            }

            if ($apply_type == 1) {
                $result['service_price'] += $attr_price;
            }

            if ($apply_type == 2) {
                $result['service_price'] -= $attr_price;
            }

            if ($apply_type == 3) {
                $result['service_price'] = $attr_price;
            }
        }


        if ($service->apply_to === 'ORDER') {
            $result['count'] = 1;
        }

        if ($service->apply_to === 'PRODUCT') {
            $result['count'] = floor($product_total_qty/$complect);
        }

        if ($service->apply_to === 'PRODUCT_UNIT') {
            $result['count'] = $product_total_qty;
        }

        if ($service->apply_to === 'CUSTOM_COUNT') {
            $result['count'] = $orderService->service_attribute;
        }

        $result['total_price'] = $result['count']*$result['service_price'];

        if($service->use_consumable) {
            $consumable = Consumable::query()->where('id', '=', $orderService->service_attribute)->first();
            $result['consumable'] = [
                'consumable_name' => $consumable->name,
                'consumable_price' => $consumable->price,
                'count' => $result['count'],
                'total_price' => $result['count']*$consumable->price,
            ];

            $result['total_price'] += $result['consumable']['total_price'];
        }

        return $result;

    }

    private function getDeliveryItems()
    {
        $result = [
            'qty' => 0,
            'total_weight' => 0,
            'total_volume' => 0,
            'total_price' => 0,
        ];


        foreach ($this->order->supply_items as $item) {
            $result['qty'] += $item['qty'];
            $result['total_weight'] += $item['weight']* $item['qty'];
            $volume = $item['width'] * $item['length'] * $item['height'] * $item['qty'];
            if ($item['unit'] == "cm") {
                $volume = $volume / 1000;
            } else {
                $volume = $volume * 1000;
            }
            $result['total_volume'] +=$volume;
        }

        $result['volume_price'] = $this->getVolumePrice($result['total_volume']);
        $result['weight_price'] = $this->getWeightPrice($result['total_weight']);
        $result['total_price'] = $result['volume_price']+$result['weight_price'];

        return $result;
    }

    private function getVolumePrice($volume)
    {
        $price = 0;
        if ($this->deliveryLimits === null) {
            return $price;
        }
        $over_volume = $volume - $this->deliveryLimits['volume_base'];

        if ($over_volume <= 0) {
            return $price;
        }

        foreach ($this->deliveryLimits['volume'] as $vol) {
            if ($over_volume >= $vol['min']) {
                if ($vol['max'] == "" || $over_volume <= $vol['max']) {
                    $price = $vol['price'];
                }
            }
        }

        return $price*$volume;
    }

    private function getWeightPrice($weight)
    {
        $price = 0;
        if ($this->deliveryLimits === null) {
            return $price;
        }
        $over_weight = $weight - $this->deliveryLimits['weight_base'];

        if ($over_weight <= 0) {
            return $price;
        }

        foreach ($this->deliveryLimits['weight'] as $wgh) {
            if ($over_weight >= $wgh['min']) {
                if ($wgh['max'] == "" || $over_weight <= $wgh['max']) {
                    $price = $wgh['price'];
                }
            }
        }

        return $price*$weight;
    }


}
