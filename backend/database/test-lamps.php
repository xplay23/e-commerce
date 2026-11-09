<?php

declare(strict_types=1);

// Synthetic fixtures only. Prices, specifications and brand names are invented.
return (static function (): array {
    $profiles = [
        ['led-bulbs', 'LED-лампочки', 'LED-лампа', 'LumoTest Basic', 3, 2, 'E27', 'A60', 7, 'гостиная, кухня, коридор', 'матовый поликарбонат', 'белый', '60 × 110 мм', '220–240 В AC', 'IP20'],
        ['filament-bulbs', 'Филаментные лампы', 'Филаментная лампа', 'LumoTest Vintage', 6, 3, 'E14', 'C35', 3, 'спальня, кафе, декоративная подсветка', 'прозрачное стекло', 'янтарный', '35 × 98 мм', '220–240 В AC', 'IP20'],
        ['spot-bulbs', 'Лампы для спотов', 'Лампа для спота', 'LumoTest Focus', 5, 2, 'GU10', 'PAR16', 4, 'кухня, витрина, акцентная подсветка', 'алюминий и стекло', 'белый', '50 × 55 мм', '220–240 В AC', 'IP20'],
        ['desk-lamps', 'Настольные лампы', 'Настольная лампа', 'LumoTest Study', 24, 8, 'встроенный LED', 'линейный рассеиватель', 5, 'рабочий стол, чтение, кабинет', 'алюминий и ABS', 'чёрный', '180 × 150 × 420 мм', 'USB-C 5 В DC; адаптер 220–240 В в комплекте', 'IP20'],
        ['floor-lamps', 'Торшеры', 'Торшер', 'LumoTest Loft', 65, 18, 'встроенный LED', 'кольцевой рассеиватель', 10, 'гостиная, чтение рядом с креслом', 'сталь и текстиль', 'бежевый', '280 × 280 × 1550 мм', '220–240 В AC', 'IP20'],
        ['pendant-lamps', 'Подвесные светильники', 'Подвесная лампа', 'LumoTest Orbit', 48, 15, 'встроенный LED', 'купольный рассеиватель', 12, 'обеденный стол, кухня, кафе', 'алюминий и стекло', 'латунь', 'Ø 300 мм; подвес 300–1200 мм', '220–240 В AC', 'IP20'],
        ['ceiling-lamps', 'Потолочные светильники', 'Потолочная лампа', 'LumoTest Panel', 32, 12, 'встроенный LED', 'круглая панель', 15, 'кухня, коридор, кабинет', 'алюминий и акрил', 'белый', 'Ø 350 × 55 мм', '220–240 В AC', 'IP20'],
        ['wall-lamps', 'Настенные светильники', 'Настенная лампа', 'LumoTest Line', 28, 9, 'встроенный LED', 'линейный рассеиватель', 6, 'спальня, коридор, подсветка зеркала вне мокрых зон', 'алюминий и акрил', 'чёрный', '300 × 80 × 65 мм', '220–240 В AC', 'IP20'],
        ['outdoor-lamps', 'Уличные светильники', 'Уличная лампа', 'LumoTest Garden', 22, 11, 'встроенный LED', 'герметичный рассеиватель', 8, 'фасад, терраса, садовая дорожка', 'алюминий и закалённое стекло', 'графит', '160 × 100 × 220 мм', '220–240 В AC', 'IP65'],
        ['smart-lamps', 'Умные лампы', 'Умная LED-лампа', 'LumoTest Connect', 15, 5, 'E27', 'A60', 7, 'гостиная, спальня, умный дом', 'матовый поликарбонат', 'белый', '60 × 118 мм', '220–240 В AC', 'IP20'],
    ];
    $products = [];
    $categories = [];
    $temperatures = [2200, 2700, 3000, 4000, 5000, 6500, 2700, 3000, 4000, 2700];
    foreach ($profiles as $group => $profile) {
        [$key, $categoryName, $type, $series, $basePrice, $priceStep, $base, $shape, $basePower, $rooms, $material, $color, $size, $voltage, $ip] = $profile;
        $categorySlug = 'test-lamps-' . $key;
        $categories[] = [
            'slug' => $categorySlug,
            'name' => $categoryName . ' (тест)',
            'description' => 'Синтетический каталог для тестирования поиска и консультаций агента. Все модели и характеристики вымышлены.',
        ];
        for ($variant = 0; $variant < 10; $variant++) {
            $number = $group * 10 + $variant + 1;
            $sku = sprintf('TEST-LAMP-%03d', $number);
            $power = $basePower + ($variant % 5) * ($group >= 4 && $group <= 6 ? 4 : 1);
            $temperature = $temperatures[$variant];
            $dimmable = $variant % 2 === 0;
            $socket = $base;
            $supply = $voltage;
            $lampShape = $shape;
            $brightness = (int) (round($power * (80 + ($variant % 3) * 10) / 10) * 10);
            $cri = [80, 90, 95][$variant % 3];
            $connection = 'нет; без приложения и хаба';
            $rgb = false;
            $battery = 'нет';
            $switch = 'внешний выключатель';
            $dimming = $dimmable ? 'да; совместимый фазовый LED-диммер TRIAC, 10–100%' : 'нет; не подключать к диммеру';
            $restrictions = ['Не является медицинским, УФ- или фитосветильником.'];
            $features = [];

            if ($group === 0 && $variant >= 5) {
                $socket = 'E14';
                $lampShape = 'C37';
                $size = '37 × 105 мм';
            } elseif ($group === 0) {
                $size = '60 × 110 мм';
            }
            if ($group === 1) {
                $socket = $variant < 5 ? 'E14' : 'E27';
                $lampShape = $variant < 5 ? 'C35' : 'ST64';
                $size = $variant < 5 ? '35 × 98 мм' : '64 × 140 мм';
                $brightness = (int) (round($power * 70 / 10) * 10);
            }
            if ($group === 2) {
                $socket = $variant < 5 ? 'GU10' : 'GU5.3';
                $lampShape = $variant < 5 ? 'PAR16' : 'MR16';
                $supply = $variant < 5 ? '220–240 В AC' : '12 В DC; блок питания приобретается отдельно';
                $angle = [24, 36, 60, 120, 36][$variant % 5];
                $features[] = "Угол света: {$angle}°.";
                if ($variant >= 5) {
                    $dimmable = false;
                    $dimming = 'нет; не подключать к диммеру';
                    $restrictions[] = 'GU5.3 на 12 В: прямое подключение к сети 220 В запрещено; не подходит для патрона GU10.';
                }
            }
            if ($group >= 3 && $group <= 8) {
                $restrictions[] = 'Светодиодный модуль встроенный; сменная лампочка с цоколем не устанавливается.';
                $dimming = $dimmable ? 'да; встроенный регулятор 10–100%, без внешнего фазового диммера' : 'нет; не подключать к диммеру';
                $switch = $dimmable ? 'сенсорная кнопка на корпусе' : 'кнопка на корпусе';
            }
            if ($group === 3) {
                $features[] = 'Поворотный плафон и регулируемый наклон стойки.';
                if ($variant >= 5) {
                    $supply = 'USB-C 5 В DC; адаптер 220–240 В в комплекте';
                    $battery = 'встроенный аккумулятор 4000 мА·ч (3,7 В); до 1 ч на максимуме, до 8 ч на минимуме';
                }
                if ($variant % 3 === 0) {
                    $temperature = '2700–6500';
                    $features[] = 'Регулируемая температура белого света, память последнего режима.';
                }
            }
            if ($group >= 5 && $group <= 8) {
                $switch = $dimmable ? 'пульт в комплекте; внешний выключатель' : 'внешний выключатель';
                $features[] = $group === 5 ? 'Высота подвеса регулируется от 300 до 1200 мм.' : 'Накладной монтаж; крепёж в комплекте.';
            }
            if ($group === 8) {
                $ip = $variant < 5 ? 'IP65' : 'IP44';
                if ($variant >= 5) {
                    $restrictions[] = 'IP44: устанавливать под навесом; не погружать в воду и не мыть струёй.';
                } else {
                    $restrictions[] = 'IP65: допускается дождь и пыль; погружение в воду запрещено.';
                }
                if ($variant % 2 === 0) $features[] = 'Датчик движения PIR: дальность 6 м, угол 120°, таймер 30–180 с.';
                $features[] = 'Рабочая температура: от −20 до +40 °C.';
                $dimmable = false;
                $dimming = 'нет; не подключать к диммеру';
                $switch = 'внешний выключатель' . ($variant % 2 === 0 ? '; автоматическое включение по датчику движения' : '');
            } else {
                $restrictions[] = 'IP20: только сухие помещения; не подходит для улицы и мокрых зон ванной.';
                $features[] = 'Рабочая температура: от +5 до +35 °C.';
            }
            if ($group === 9) {
                $socket = $variant === 9 ? 'E14' : 'E27';
                $lampShape = $variant === 9 ? 'C37' : 'A60';
                $size = $variant === 9 ? '37 × 110 мм' : '60 × 118 мм';
                $dimmable = true;
                $dimming = 'да; через приложение 1–100%; внешний фазовый диммер не поддерживается';
                $temperature = '2200–6500';
                $rgb = $variant % 2 === 0;
                $connection = match ($variant % 3) {
                    0 => 'Wi-Fi 2,4 ГГц; хаб не нужен; Wi-Fi 5 ГГц не поддерживается',
                    1 => 'Zigbee 3.0; необходим совместимый Zigbee-хаб, приобретается отдельно',
                    2 => 'Matter over Thread; необходимы Thread border router и Matter-контроллер, приобретаются отдельно',
                };
                $switch = 'приложение; расписания; группы света';
                $features[] = 'Требуется постоянное питание для управления через приложение.';
                $restrictions[] = 'Поддержку конкретной голосовой платформы нужно проверить отдельно; универсальная совместимость не заявлена.';
            }
            if ($socket === 'E14' || $socket === 'E27' || $socket === 'GU10' || $socket === 'GU5.3') {
                $restrictions[] = "Проверьте совпадение цоколя {$socket}, напряжения и габаритов светильника перед подбором.";
            }
            $kelvin = is_int($temperature) ? $temperature : null;
            $light = $kelvin === null ? 'регулируемый белый свет' : ($kelvin <= 3000 ? 'тёплый свет' : ($kelvin <= 4500 ? 'нейтральный свет' : 'холодный свет'));
            $price = $basePrice + $variant * $priceStep;
            $stock = $variant === 9 ? 0 : ($variant === 8 ? 1 : 5 + (($number * 7) % 76));
            $specs = [
                'Артикул' => $sku,
                'Бренд' => 'LumoTest (вымышленный)',
                'Тип' => $type,
                'Технология' => $group === 1 ? 'LED filament' : 'LED',
                'Цоколь' => $socket,
                'Форма' => $lampShape,
                'Мощность' => "{$power} Вт",
                'Световой поток' => "{$brightness} лм",
                'Цветовая температура' => "{$temperature} K; {$light}",
                'Цветопередача' => "CRI Ra {$cri}",
                'Диммирование' => $dimming,
                'RGB' => $rgb ? 'да; цветной свет и отдельный регулируемый белый канал' : 'нет; только белый свет',
                'Питание' => $supply,
                'Аккумулятор' => $battery,
                'Управление' => $switch,
                'Подключение' => $connection,
                'Степень защиты' => $ip,
                'Материал' => $material,
                'Цвет корпуса' => $color,
                'Размеры' => $size,
                'Назначение' => $rooms,
                'Ресурс' => (20000 + ($variant % 4) * 5000) . ' ч (условное тестовое значение)',
                'Гарантия' => ($variant % 3 + 1) * 12 . ' мес. (условное тестовое значение)',
                'Комплект' => $group <= 2 || $group === 9 ? '1 лампа; инструкция' : '1 светильник; инструкция; указанные выше аксессуары',
            ];
            $description = "ТЕСТОВЫЕ ДАННЫЕ. Вымышленный товар для проверки поиска и консультаций агента; все параметры, цены и остатки синтетические.\n\n";
            foreach ($specs as $label => $value) $description .= "{$label}: {$value}.\n";
            $description .= "\nОсобенности: " . implode(' ', $features) . "\nОграничения: " . implode(' ', $restrictions);
            $products[] = [
                'category_slug' => $categorySlug,
                'name' => sprintf('%s %s %02d — %s (тест)', $type, $series, $variant + 1, $sku),
                'slug' => sprintf('test-lamp-%03d', $number),
                'description' => $description,
                'short_description' => "Тестовая модель. {$socket}; {$power} Вт; {$brightness} лм; {$temperature} K; {$ip}. " . ($dimmable ? 'С диммированием.' : 'Без диммирования.'),
                'price' => $price,
                'old_price' => $variant % 4 === 0 ? round($price * 1.2, 2) : null,
                'stock' => $stock,
                'image_url' => null,
                'is_active' => true,
                'is_featured' => false,
            ];
        }
    }
    return ['categories' => $categories, 'products' => $products];
})();
