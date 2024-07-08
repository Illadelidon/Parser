<?php

require __DIR__ . "/vendor/autoload.php";

use GuzzleHttp\Client;

$url = 'https://slotcatalog.com/en/Providers';

$client = new Client([
    'verify' => false, // Вимкнути перевірку сертифікатів
]);

try {
    $response = $client->get($url);
    $html = $response->getBody()->getContents();



    $document = new \DiDom\Document();
    $document->loadHTML($html);


    $gameItems = $document->find('.gameItemimg');
    $base_url = 'https://slotcatalog.com';

// Відкриваємо CSV файл для запису (файл буде створений автоматично, якщо не існує)
    $csvFile = fopen('providers_data.csv', 'w');

// Записуємо заголовки CSV файлу
    fputcsv($csvFile, [
        'Title', 'URL', 'Image URL', 'Casino Count', 'Provider Rank', 'Founded',
        'Website', 'Licenses', 'Total Games', 'Video Slots', 'Card games',
        'Roulette Games', 'Dice Games', 'Scratch tickets', 'Other types', 'Countries'
    ]);

    foreach ($gameItems as $item) {
        $link = $item->first('a');
        $img = $item->first('img');

        if ($link && $img) {
            $title = $link->attr('title');
            $relative_url = $link->attr('href');
            $absolute_url = $base_url . $relative_url;
            $data_src = $img->attr('data-src');
            $src = $img->attr('src');
            $image_url = $data_src ? $data_src : $src;

            var_dump($title, $absolute_url, $image_url);
            echo '<br>';

            $provider_response = $client->get($absolute_url);
            $provider_html = $provider_response->getBody()->getContents();

            $provider_document = new \DiDom\Document();
            $provider_document->loadHTML($provider_html);

            // Парсинг кількості казино
            $casino_count_element = $provider_document->first('.provider_prop_item_pad a[href="#aBrandCasino"] .prop_number');
            if ($casino_count_element) {
                $casino_count = $casino_count_element->text();
                echo "The number of casinos: " . $casino_count . "<br>";
            } else {
                echo "Number of casinos not found.<br>";
            }

            $attributes_selectors = [
                'Provider Rank' => '.widgetProviderRunkAttr .providerWidget',
                'Founded' => 'td[data-label="Founded"]',
                'Website' => 'td[data-label="Website"] a',
                'Licenses' => 'td[data-label="Licenses"]',
                'Total Games' => 'td[data-label="Total Games"] a',
                'Video Slots' => 'td[data-label="Video Slots"] a',
                'Card games' => 'td[data-label="Card games"] a',
                'Roulette Games' => 'td[data-label="Roulette Games"] a',
                'Dice Games' => 'td[data-label="Dice Games"] a',
                'Scratch tickets' => 'td[data-label="Scratch tickets"] a',
                'Other types' => 'td[data-label="Other types"] a'
            ];

            // Парсинг атрибутів
            $attributes_section = $provider_document->first('.provFormalAttr');
            if ($attributes_section) {
                $attributes = [];

                foreach ($attributes_selectors as $key => $selector) {
                    $element = $attributes_section->first($selector);
                    if ($element) {
                        if ($key === 'Website') {
                            $attributes[$key] = $element->attr('href');
                        } else {
                            $attributes[$key] = $element->text();
                        }
                    } else {
                        $attributes[$key] = 'N/A';
                    }
                }

                // Виведення атрибутів
                foreach ($attributes as $key => $value) {
                    echo $key . ": " . $value . "<br>";
                }
            } else {
                echo "Attributes not found.<br>";
            }
            // Парсинг списку країн
            $countries = [];
            $tables = $provider_document->find('table');
            foreach ($tables as $table) {
                $headers = $table->find('th');
                foreach ($headers as $header) {
                    if (trim($header->text()) === 'Country') {
                        $rows = $table->find('tbody tr');
                        foreach ($rows as $row) {
                            $country_name_element = $row->first('td[data-label="Country"] .linkOneLine');
                            if ($country_name_element) {
                                $countries[] = $country_name_element->text();
                            }
                        }
                        break 2; // Виходимо з обох циклів після знаходження потрібної таблиці
                    }
                }
            }

            $countries_list = implode('; ', $countries);

            // Записуємо дані у CSV файл
            fputcsv($csvFile, [
                $title, $absolute_url, $image_url, $casino_count, $attributes['Provider Rank'],
                $attributes['Founded'], $attributes['Website'], $attributes['Licenses'],
                $attributes['Total Games'], $attributes['Video Slots'], $attributes['Card games'],
                $attributes['Roulette Games'], $attributes['Dice Games'], $attributes['Scratch tickets'],
                $attributes['Other types'], $countries_list
            ]);
        }
    }
    fclose($csvFile);

    echo "Parsing is complete and the data is saved to the providers_data.csv file";
}
catch (\GuzzleHttp\Exception\RequestException $e) {
    echo "Error connecting to the site: " . $e->getMessage();
}catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}