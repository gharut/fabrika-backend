<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Order\OrderCalculator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Orders\OrderCreateRequest;
use App\Http\Requests\Api\Orders\OrderUpdatePackagingRequest;
use App\Http\Requests\Api\Orders\OrderUpdatePickupRequest;
use App\Http\Requests\Api\Orders\OrderUpdateStatusRequest;
use App\Models\Order;
use App\Models\OrderService;
use App\Models\Product;
use \Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrdersController extends Controller
{
    public function list() {
        return \response()->json([
            'data' => Order::all()->load(['client'])
        ]);
    }

    public function getOrder(Request $request) {
        $order = Order::with(['client','products', 'order_services'])->find($request->route('id'));
        return \response()->json([
            'success' => (bool)$order,
            'data' => $order
        ]);
    }
    public function store(OrderCreateRequest $request) {
        $saved = true;
        DB::beginTransaction();
        try{
            $order = new Order();

            $order->client_id = $request->input('client');
            $order->supply_date = date("Y-m-d H:i:s", strtotime($request->input('pickup.supply_date')));
            $order->supply_time = $request->input('pickup.supply_time');
            $order->supply_items = $request->input('pickup.supply_items');
            $order->supply_details = $request->input('pickup.supply_details');
            $order->save();


            $product_index_id_map = [];
            foreach ($request->input('pickup.products', []) as $idx=>$prd) {
                $product = new Product();
                $product->order_id = $order->id;
                $product->fill($prd);
                $qty = 0;
                foreach ($prd['size'] as $size) {
                    $qty += $size['qty'];
                }
                $product->qty = $qty;
                $product->save();
                $product_index_id_map[$idx] = $product->id;
                if($prd['child']) {
                    foreach ($prd['child'] as $chp) {
                        $child = new Product();
                        $child->parent_id = $product->id;
                        $child->order_id = $order->id;
                        $child->name = $product->name;
                        $child->fill($chp);


                        $qty = 0;
                        foreach ($chp['size'] as $size) {
                            $qty += $size['qty'];
                        }
                        $child->qty = $qty;
                        $child->save();
                    }
                }
            }

            foreach($request->input('pickup.services', []) as $service) {
                if($service['service_enabled']) {
                    $orderService = new OrderService();
                    $orderService->fill($service);
                    $orderService->order_id = $order->id;
                    $orderService->save();
                }
            }

            foreach ($request->get('packaging', []) as $pkg) {
                $product_id = $product_index_id_map[$pkg['product_id']];
                $product = Product::query()->where('id','=',$product_id)->first();
                if(!$product->exists) {
                    throw new \Exception("Wrong data");
                }

                $product->update(['complect'=>$pkg["complect"]]);

                foreach ($pkg['services'] as $service) {
                    if($service['service_enabled']) {
                        $orderService = new OrderService();
                        $orderService->fill($service);
                        $orderService->order_id = $order->id;
                        $orderService->product_id = $product_id;
                        $orderService->save();
                    }
                }
            }

            DB::commit();
        }catch (\Exception $e) {
            DB::rollBack();
            print_r($e->getMessage());
            $saved = false;
        }

        return \response()->json([
            'success' => $saved,
            'data' => $order
        ]);
    }

    public function updatePickup(Order $order, OrderUpdatePickupRequest $request) {
        $saved = true;
        DB::beginTransaction();
        try{
            $order->supply_date = date("Y-m-d H:i:s", strtotime($request->input('supply_date')));
            $order->supply_time = $request->input('supply_time');
            $order->supply_items = $request->input('supply_items');
            $order->supply_details = $request->input('supply_details');
            $order->save();

            $orderServices = $order->order_services;
            $inputServices = $request->input('services');
            foreach ($orderServices as $service) {
                foreach ($inputServices as $idx=>$input_svc) {

                    if($service->service_id == $input_svc['service_id']) {

                        if(!$input_svc['service_enabled']) {
                            $service->delete();
                        }else{
                            if($service->service_attribute != $input_svc['service_attribute']) {
                                $service->service_attribute = $input_svc['service_attribute'];
                                $service->save();
                            }
                        }
                        unset($inputServices[$idx]);
                    }
                }
            }

            foreach ($inputServices as $service) {
                if($service['service_enabled']) {
                    $orderService = new OrderService();
                    $orderService->fill($service);
                    $orderService->order_id = $order->id;
                    $orderService->save();
                }
            }

            DB::commit();
        }catch (\Exception $e) {
            DB::rollBack();
            print_r($e->getMessage());
            $saved = false;
        }

        return \response()->json([
            'success' => $saved,
            'data' => $order->load(['client','products', 'order_services'])
        ]);
    }

    public function updateStatus(Order $order, OrderUpdateStatusRequest $request) {
        $order->load(['client','products', 'order_services']);
        $order->setAttribute($request->input('attribute'), $request->input('value'));
        $saved = $order->save();
        return \response()->json([
            'success' => $saved,
            'data' => $order,
        ]);
    }
    public function updatePackaging(Order $order, OrderUpdatePackagingRequest $request) {

        $saved = true;
        $order->load(['products','order_services']);
        DB::beginTransaction();
        try{
            $products = [];
            $newProducts = [];
            foreach($request->input('products') as $prd) {
                if($prd['product_id'] != null) {
                    $products[$prd['product_id']] = $prd;
                    $products[$prd['product_id']]['childs'] = [];
                    $products[$prd['product_id']]['newChilds'] = [];
                    if(is_array($products[$prd['product_id']]['child'])) {
                        foreach ($products[$prd['product_id']]['child'] as $ch){
                            if($ch['product_id'] != null) {
                                $products[$prd['product_id']]['childs'][$ch['product_id']] = $ch;
                            }else{
                                $products[$prd['product_id']]['newChilds'][] = $ch;
                            }
                        }
                    }
                }else{
                    $newProducts[]=$prd;
                }
            }

//            echo count($products);
//            print_r($order->products->toJSON());
            foreach ($order->products as $prd) {

                if($prd->parent_id != null) {
                    continue;
                }
                $prd->load(['children', 'services']);

                if(!array_key_exists($prd->id, $products)){
                    $prd->delete();
                    continue;
                }


                $product = $products[$prd->id];

                $qty = 0;
                $prd->name = $product['name'];

                $prd->color = $product['color'];
                $prd->complect = $product['complect'];
                $prd->size = $product['size'];

                foreach ($product['size'] as $sz) {
                    $qty += $sz['qty'];
                }
                $prd->qty = $qty;

                foreach($prd->children as $child) {
                    if(!array_key_exists($child->id, $product['childs'])) {
                        $child->delete();
                        continue;
                    }
                    $ch = $product['childs'][$child->id];
                    $child->name = $product['name'];
                    $child->color = $ch['color'];
                    $child->size = $ch['size'];
                    $qty = 0;
                    foreach ($ch['size'] as $sz) {
                        $qty += $sz['qty'];
                    }
                    $child->qty = $qty;

                    $child->save();
                }

                $prd->save();

                foreach ($product['newChilds'] as $nch) {
                    $newChild = new Product();
                    $newChild->parent_id = $prd->id;
                    $newChild->order_id = $order->id;
                    $newChild->name = $prd->name;
                    $newChild->fill($nch);


                    $nqty = 0;
                    foreach ($nch['size'] as $size) {
                        $nqty += $size['qty'];

                    }
                    $newChild->qty = $nqty;
                    $newChild->save();
                }

                foreach ($product['services'] as $svc) {
                    if($svc['record_id'] != null) {
                        $service = OrderService::where('id' , '=' , $svc['record_id'] )->first();
                        if($svc['service_enabled']) {
                            $service->service_attribute = $svc['service_attribute'];
                            $service->save();
                        }else{
                            $service->delete();
                        }
                    }else{
                        if($svc['service_enabled']) {
                            $service = new OrderService();
                            $service->order_id = $order->id;
                            $service->product_id = $prd->id;
                            $service->service_id = $svc['service_id'];
                            $service->service_attribute = $svc['service_attribute'];
                            $service->save();
                        }

                    }
                }

            }

            foreach ($newProducts as $prd) {
                $product = new Product();
                $product->order_id = $order->id;
                $product->fill($prd);
                $qty = 0;
                foreach ($prd['size'] as $size) {
                    $qty += $size['qty'];
                }
                $product->qty = $qty;
                $product->save();

                if($prd['child']) {
                    foreach ($prd['child'] as $chp) {
                        $child = new Product();
                        $child->parent_id = $product->id;
                        $child->order_id = $order->id;
                        $child->name = $product->name;
                        $child->fill($chp);


                        $qty = 0;
                        foreach ($chp['size'] as $size) {
                            $qty += $size['qty'];
                        }
                        $child->qty = $qty;
                        $child->save();
                    }
                }
                if($prd['services']){
                    foreach ($prd['services'] as $svc) {
                        if($svc['service_enabled']) {
                            $service = new OrderService();
                            $service->order_id = $order->id;
                            $service->product_id = $product->id;
                            $service->service_id = $svc['service_id'];
                            $service->service_attribute = $svc['service_attribute'];
                            $service->save();
                        }
                    }
                }

            }

            DB::commit();
        }catch (\Exception $e) {
            DB::rollBack();
            echo(json_encode([$e->getMessage(), $e->getFile(), $e->getLine()]));
            $saved = false;
        }

        return \response()->json([
            'success' => $saved,
            'data' => $order->load(['client','products', 'order_services'])
        ]);
    }

    public function calculate(Request $request) {
        $calculation = $this->getOrderCalculation($request->route('id'));
        return \response()->json([
            'success' => true,
            'data' => $calculation
        ]);
    }

    private function getOrderCalculation(string $order_uuid) {
        $order = Order::with(['products', 'order_services'])->where('uuid', $order_uuid)->firstOrFail();
        $calc = new OrderCalculator($order);

        return $calc->getResult();
    }

    private function err($data) {
        throw new \Exception(json_encode($data));
    }
}
