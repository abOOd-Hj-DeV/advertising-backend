<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Location;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    /** @return array<int, array{slug:string,name:string,icon:string,children?:array<int,array{slug:string,name:string,attrs?:array<int,array{key:string,label:string,type:string,required?:bool,filterable?:bool,options?:array<int,string>}}>}> */
    private function categories(): array
    {
        return [
            ['slug' => 'vehicles', 'name' => 'سيارات ومركبات', 'icon' => 'car', 'children' => [
                ['slug' => 'cars', 'name' => 'سيارات', 'attrs' => [
                    ['key' => 'brand', 'label' => 'الشركة', 'type' => 'enum', 'required' => true, 'filterable' => true, 'options' => ['كيا', 'هيونداي', 'تويوتا', 'شيفروليه', 'مرسيدس', 'BMW', 'سابا', 'أخرى']],
                    ['key' => 'model', 'label' => 'الموديل', 'type' => 'text', 'required' => true],
                    ['key' => 'year', 'label' => 'سنة الصنع', 'type' => 'int', 'required' => true, 'filterable' => true],
                    ['key' => 'fuel', 'label' => 'الوقود', 'type' => 'enum', 'filterable' => true, 'options' => ['بنزين', 'ديزل', 'كهرباء', 'هجين']],
                    ['key' => 'kilometers', 'label' => 'الكيلومترات', 'type' => 'int', 'filterable' => true],
                    ['key' => 'transmission', 'label' => 'ناقل الحركة', 'type' => 'enum', 'filterable' => true, 'options' => ['عادي', 'أوتوماتيك']],
                ]],
                ['slug' => 'motorcycles', 'name' => 'دراجات نارية'],
                ['slug' => 'auto-parts', 'name' => 'قطع وإكسسوارات'],
                ['slug' => 'trucks-commercial', 'name' => 'شاحنات ومركبات تجارية'],
            ]],
            ['slug' => 'real-estate', 'name' => 'عقارات', 'icon' => 'home', 'children' => [
                ['slug' => 'apartments', 'name' => 'شقق', 'attrs' => [
                    ['key' => 'offer', 'label' => 'العرض', 'type' => 'enum', 'required' => true, 'filterable' => true, 'options' => ['بيع', 'إيجار']],
                    ['key' => 'rooms', 'label' => 'الغرف', 'type' => 'int', 'filterable' => true],
                    ['key' => 'area_m2', 'label' => 'المساحة (م²)', 'type' => 'decimal', 'filterable' => true],
                    ['key' => 'floor', 'label' => 'الطابق', 'type' => 'int'],
                    ['key' => 'furnished', 'label' => 'مفروشة', 'type' => 'bool', 'filterable' => true],
                ]],
                ['slug' => 'houses', 'name' => 'منازل'],
                ['slug' => 'lands', 'name' => 'أراضٍ'],
                ['slug' => 'commercial-property', 'name' => 'محلات ومكاتب'],
            ]],
            ['slug' => 'electronics', 'name' => 'إلكترونيات', 'icon' => 'devices', 'children' => [
                ['slug' => 'phones', 'name' => 'هواتف', 'attrs' => [
                    ['key' => 'brand', 'label' => 'الشركة', 'type' => 'enum', 'filterable' => true, 'options' => ['آيفون', 'سامسونج', 'شاومي', 'هواوي', 'أخرى']],
                    ['key' => 'condition', 'label' => 'الحالة', 'type' => 'enum', 'filterable' => true, 'options' => ['جديد', 'مستعمل']],
                ]],
                ['slug' => 'computers', 'name' => 'حواسيب ولابتوبات'],
                ['slug' => 'tvs-audio', 'name' => 'تلفزيونات وصوتيات'],
                ['slug' => 'home-appliances', 'name' => 'أجهزة منزلية'],
            ]],
            ['slug' => 'furniture', 'name' => 'أثاث ومنزل', 'icon' => 'chair', 'children' => [
                ['slug' => 'living-furniture', 'name' => 'أثاث'],
                ['slug' => 'kitchen', 'name' => 'مطبخ وأدوات'],
                ['slug' => 'decor', 'name' => 'ديكور وإكسسوارات'],
            ]],
            ['slug' => 'fashion', 'name' => 'أزياء وملابس', 'icon' => 'shirt', 'children' => [
                ['slug' => 'mens-clothing', 'name' => 'رجالي'],
                ['slug' => 'womens-clothing', 'name' => 'نسائي'],
                ['slug' => 'kids-clothing', 'name' => 'أطفال'],
            ]],
            ['slug' => 'jobs', 'name' => 'وظائف', 'icon' => 'briefcase', 'children' => [
                ['slug' => 'job-offers', 'name' => 'وظائف شاغرة'],
                ['slug' => 'job-seekers', 'name' => 'باحثون عن عمل'],
            ]],
            ['slug' => 'services', 'name' => 'خدمات', 'icon' => 'tools', 'children' => [
                ['slug' => 'repair-services', 'name' => 'صيانة وتصليح'],
                ['slug' => 'transport-services', 'name' => 'نقل وتوصيل'],
                ['slug' => 'education-services', 'name' => 'تعليم ودورات'],
            ]],
            ['slug' => 'hobbies', 'name' => 'هوايات وترفيه', 'icon' => 'gamepad', 'children' => [
                ['slug' => 'books-media', 'name' => 'كتب ووسائط'],
                ['slug' => 'sports', 'name' => 'رياضة'],
                ['slug' => 'gaming', 'name' => 'ألعاب فيديو'],
            ]],
            ['slug' => 'animals', 'name' => 'حيوانات', 'icon' => 'paw', 'children' => [
                ['slug' => 'pets', 'name' => 'حيوانات أليفة'],
                ['slug' => 'livestock', 'name' => 'ماشية ودواجن'],
            ]],
            ['slug' => 'miscellaneous', 'name' => 'متفرقات', 'icon' => 'apps'],
        ];
    }

    /** @return array<string, list<string>> */
    private function locations(): array
    {
        return [
            'دمشق' => ['دمشق', 'المزة', 'الميدان', 'الشعلان', 'برزة', 'القابون', 'دمر', 'كفرسوسة', 'المهاجرين', 'باب توما'],
            'ريف دمشق' => ['جرمانا', 'داريا', 'صحنايا', 'المليحة', 'التل', 'حرستا', 'دوما', 'قطنا', 'يبرود', 'الزبداني'],
            'حلب' => ['حلب', 'السليمانية', 'الفرقان', 'الجامعة', 'السبيل', 'اعزاز', 'الباب', 'منبج', 'عفرين', 'تل رفعت'],
            'حمص' => ['حمص', 'الوعر', 'الإنشاءات', 'حماة المدينة', 'تلكلخ', 'القصير', 'الرستن'],
            'حماة' => ['حماة', 'السلمية', 'مصياف', 'محردة', 'سقلبية'],
            'اللاذقية' => ['اللاذقية', 'جبلة', 'القرداحة', 'الحفة'],
            'طرطوس' => ['طرطوس', 'بانياس', 'صافيتا', 'الدريكيش', 'الشيخ بدر'],
            'إدلب' => ['إدلب', 'معرة النعمان', 'أريحا', 'جسر الشغور', 'سراقب'],
            'دير الزور' => ['دير الزور', 'الميادين', 'البوكمال'],
            'الرقة' => ['الرقة', 'الطبقة'],
            'الحسكة' => ['الحسكة', 'القامشلي', 'رأس العين', 'المالكية'],
            'درعا' => ['درعا', 'إزرع', 'الصنمين', 'نوى', 'جاسم'],
            'السويداء' => ['السويداء', 'صلخد', 'شهبا'],
            'القنيطرة' => ['القنيطرة', 'خان أرنبة'],
        ];
    }

    public function run(): void
    {
        $order = 0;
        foreach ($this->categories() as $cat) {
            $parent = Category::query()->updateOrCreate(
                ['slug' => $cat['slug']],
                ['name_ar' => $cat['name'], 'icon' => $cat['icon'], 'sort_order' => $order++, 'is_active' => true]
            );
            foreach ($cat['children'] ?? [] as $childOrder => $child) {
                $childModel = Category::query()->updateOrCreate(
                    ['slug' => $child['slug']],
                    ['name_ar' => $child['name'], 'parent_id' => $parent->id, 'sort_order' => $childOrder, 'is_active' => true]
                );
                $attrOrder = 0;
                foreach ($child['attrs'] ?? [] as $attr) {
                    $attrModel = $childModel->attributes()->updateOrCreate(
                        ['key' => $attr['key']],
                        [
                            'label_ar' => $attr['label'],
                            'data_type' => $attr['type'],
                            'is_required' => $attr['required'] ?? false,
                            'is_filterable' => $attr['filterable'] ?? false,
                            'sort_order' => $attrOrder++,
                        ]
                    );
                    foreach ($attr['options'] ?? [] as $optOrder => $option) {
                        $attrModel->options()->updateOrCreate(
                            ['value' => Str::slug($option)],
                            ['label_ar' => $option, 'sort_order' => $optOrder]
                        );
                    }
                }
            }
        }

        foreach ($this->locations() as $governorate => $cities) {
            foreach ($cities as $city) {
                Location::query()->updateOrCreate(
                    ['governorate' => $governorate, 'city' => $city],
                    []
                );
            }
        }
    }
}
