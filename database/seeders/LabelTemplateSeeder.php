<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LabelTemplate;

class LabelTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'id' => 1,
                'name' => 'ШК',
                'is_system' => true,
                'content' => '{"page":{"w":58,"h":40,"pad":1,"grid":0.5,"snap":true},"blocks":[{"id":"t1","type":"text","x":2.5,"y":4.5,"w":53.5,"h":6,"text":"{{name}}","style":{"bold":true,"align":"center","size":2.7}},{"id":"t6icuq","type":"text","x":2.5,"y":11.5,"w":33.5,"h":3.5,"text":"Артикул: {{article}} ","style":{"size":2.4}},{"id":"tj05ij","type":"text","x":2.5,"y":18,"w":33.5,"h":3.5,"text":"Цвет: {{color}}","style":{"size":2.4}},{"id":"tr1kya","type":"text","x":2.5,"y":14.5,"w":33.5,"h":3.5,"text":"{{client}}","style":{"size":2.3}},{"id":"tqvrm2","type":"text","x":2.5,"y":21,"w":53.5,"h":3.5,"text":"Состав: {{composition}}","style":{"size":2.3}},{"id":"t88u4b","type":"text","x":36.5,"y":12,"w":19.5,"h":8,"text":"{{size}}","style":{"size":3.2,"align":"center","bold":true}},{"id":"imgoeuug","type":"barcode","x":14.5,"y":24.5,"w":29.5,"h":15,"src":""},{"id":"tbhhpr","type":"text","x":2.5,"y":1,"w":53.5,"h":4,"text":"{{brand}}","style":{"size":2.7,"bold":true,"align":"center"}}]}',
            ],
            [
                'id' => 2,
                'name' => 'ЧЗ',
                'is_system' => true,
                'content' => '{"page":{"w":58,"h":40,"pad":1,"grid":0.5,"snap":true},"blocks":[{"id":"imgj6bt4","type":"datamatrix","x":2.5,"y":5,"w":23,"h":23},{"id":"toixha","type":"text","x":27,"y":33.5,"w":28,"h":4,"text":"{{serial}}","style":{"size":2.4}},{"id":"t30gjv","type":"text","x":2.5,"y":33.5,"w":23.5,"h":4,"text":"{{gtin}}","style":{"size":2.4}},{"id":"imgdxfxc","type":"czLogo","x":30.5,"y":2,"w":23,"h":5.5},{"id":"tkj0hd","type":"text","x":28,"y":9,"w":27,"h":19,"text":"{{name}}, {{color}}, {{size}}","style":{"size":2.2}},{"id":"t3ecui","type":"text","x":23,"y":28.5,"w":31,"h":4.5,"text":"{{number}}","style":{"size":3,"bold":true,"align":"right"}},{"id":"thjdqz","type":"text","x":2.5,"y":2,"w":23,"h":3,"text":"{{userId}}","style":{"size":2}}]}',
            ],
            [
                'id' => 3,
                'name' => 'Совмещенное',
                'is_system' => true,
                'content' => '{"page":{"w":58,"h":40,"pad":1,"grid":0.5,"snap":true},"blocks":[{"id":"t1","type":"text","x":2,"y":2,"w":29.5,"h":3.5,"text":"{{brand}}","style":{"underline":true,"italic":true,"bold":true,"align":"center","size":2.3}},{"id":"toqz7c","type":"text","x":2,"y":5.5,"w":29.5,"h":5.5,"text":"{{name}}","style":{"size":2.2,"align":"center","bold":true}},{"id":"t3zz4v","type":"text","x":2,"y":11.5,"w":29.5,"h":3.5,"text":"{{size}}","style":{"size":2.3}},{"id":"ttgoaw","type":"text","x":2,"y":15,"w":29.5,"h":4,"text":"Цвет: {{color}}","style":{"size":2.3}},{"id":"imgj6bt4","type":"datamatrix","x":32,"y":2,"w":21,"h":21},{"id":"toixha","type":"text","x":32,"y":23.5,"w":23,"h":3,"text":"(01){{gtin}}","style":{"size":1.8}},{"id":"t30gjv","type":"text","x":32,"y":27,"w":23,"h":3,"text":"(21){{serial}}","style":{"size":1.8}},{"id":"t8dj59","type":"text","x":2,"y":19,"w":29.5,"h":5.5,"text":"Состав: {{composition}}","style":{"size":1.9}},{"id":"imgmn34s","type":"barcode","x":2,"y":24.5,"w":28,"h":13.5},{"id":"imgdxfxc","type":"czLogo","x":30.5,"y":30.5,"w":14.5,"h":6},{"id":"tt2tz4","type":"text","x":46,"y":32,"w":9.5,"h":3.5,"text":"{{number}}","style":{"size":2,"align":"center","bold":true}}]}',
            ],
        ];

        foreach ($templates as $template) {
            LabelTemplate::updateOrCreate(
                ['id' => $template['id']],
                $template
            );
        }
    }
}
