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
        LabelTemplate::insert([
            [
                'name' => 'ШК',
                'is_system' => true,
                'content' => '{"page":{"w":58,"h":40,"pad":1,"grid":0.5,"snap":true},"blocks":[{"id":"t1","type":"text","x":1,"y":1,"w":55,"h":7.5,"text":"{{name}}","style":{"bold":true,"align":"center","size":3}},{"id":"t6icuq","type":"text","x":1,"y":9,"w":34,"h":3.5,"text":"Артикул: {{article}} ","style":{"size":2.6}},{"id":"tj05ij","type":"text","x":1,"y":16,"w":34,"h":3.5,"text":"Цвет: {{color}}","style":{"size":2.7}},{"id":"tr1kya","type":"text","x":1,"y":12.5,"w":34,"h":3.5,"text":"{{client}}","style":{"size":2.7}},{"id":"tqvrm2","type":"text","x":1,"y":19.5,"w":55,"h":3.5,"text":"Состав: {{composition}}","style":{"size":2.7}},{"id":"t88u4b","type":"text","x":35,"y":9,"w":21,"h":10.5,"text":"{{size}}","style":{"size":3.5,"align":"center","bold":true}},{"id":"imgoeuug","type":"barcode","x":15,"y":24,"w":27.5,"h":15.5,"src":""}]}',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'ЧЗ',
                'is_system' => true,
                'content' => '{"page":{"w":58,"h":40,"pad":1,"grid":0.5,"snap":true},"blocks":[{"id":"imgj6bt4","type":"datamatrix","x":2,"y":2,"w":24,"h":24},{"id":"toixha","type":"text","x":31,"y":33,"w":23.5,"h":4,"text":"78123712731","style":{"size":2.5}},{"id":"t30gjv","type":"text","x":2,"y":33,"w":23.5,"h":4,"text":"0928178921","style":{"size":2.5}},{"id":"imgdxfxc","type":"czLogo","x":30.5,"y":2,"w":23,"h":5.5},{"id":"tkj0hd","type":"text","x":28,"y":8.5,"w":27,"h":18.5,"text":"{{name}}, {{color}}, {{size}}","style":{"size":2.2}},{"id":"t3ecui","type":"text","x":23,"y":27.5,"w":32,"h":5,"text":"{{id}}","style":{"size":3,"bold":true,"align":"right"}}]}',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Совмещенное',
                'is_system' => true,
                'content' => '{"page":{"w":58,"h":40,"pad":1,"grid":0.5,"snap":true},"blocks":[{"id":"t1","type":"text","x":1,"y":0.5,"w":30,"h":3.5,"text":"{{brand}}","style":{"underline":true,"italic":true,"bold":true,"align":"center","size":2.3}},{"id":"toqz7c","type":"text","x":1,"y":3.5,"w":30,"h":6.5,"text":"{{name}}","style":{"size":2.2,"align":"center","bold":true}},{"id":"t3zz4v","type":"text","x":1,"y":10,"w":30,"h":4,"text":"{{size}}","style":{"size":2.3}},{"id":"ttgoaw","type":"text","x":1,"y":14,"w":30,"h":4,"text":"Цвет: {{color}}","style":{"size":2.3}},{"id":"imgj6bt4","type":"datamatrix","x":32,"y":0.5,"w":20,"h":20},{"id":"toixha","type":"text","x":32.5,"y":22.5,"w":23.5,"h":3,"text":"78123712731","style":{"size":1.8}},{"id":"t30gjv","type":"text","x":32.5,"y":25,"w":23.5,"h":3,"text":"0928178921","style":{"size":1.8}},{"id":"t8dj59","type":"text","x":1,"y":18,"w":30,"h":6,"text":"Состав: {{composition}}","style":{"size":1.9}},{"id":"imgmn34s","type":"barcode","x":1,"y":24,"w":30,"h":14.5},{"id":"imgdxfxc","type":"czLogo","x":32.5,"y":30,"w":15,"h":5.5}]}',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
