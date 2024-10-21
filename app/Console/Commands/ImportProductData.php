<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportProductData extends Command
{
    protected $signature = 'import:productdata {path} {--test}'; // Опция --test для тестирования
    protected $description = 'Импорт данных о продуктах из CSV';

    public function handle()
    {
        $filePath = $this->argument('path');

        // Проверяем, существует ли файл
        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->error('Файл не найден или недоступен для чтения.');
            return;
        }

        // Открываем CSV файл
        if (($handle = fopen($filePath, 'r')) !== false) {
            // Считываем заголовки
            $headers = fgetcsv($handle);

            // Обработка оставшихся строк
            $processedCount = 0;
            $successCount = 0;
            $errorCount = 0;
            $errorRows = [];  // Массив для хранения строк с ошибками

            while (($row = fgetcsv($handle)) !== false) {
                // Увеличиваем счетчик обработанных строк
                $processedCount++;

                // Пропуск пустых строк
                if (empty(array_filter($row))) {
                    continue;
                }

                // Проверяем, что количество элементов совпадает с заголовками
                if (count($headers) !== count($row)) {
                    Log::warning("Строка с ошибкой: " . implode(',', $row));
                    $errorRows[] = implode(',', $row); // Сохраняем ошибочную строку
                    $errorCount++;
                    continue;
                }

                // Сопоставляем заголовки с данными строки
                $data = array_combine($headers, $row);

                // Проверяем обязательные поля
                if (empty($data['Product Code']) || empty($data['Product Name']) || empty($data['Product Description']) || (empty($data['Stock']) && $data['Stock'] !== '0')) {
                    Log::warning("Пропущенная строка из-за незаполненных обязательных полей: " . implode(',', $data));
                    $errorRows[] = implode(',', $data); // Сохраняем ошибочную строку
                    $errorCount++;
                    continue;
                }

                // Преобразуем стоимость в числовое значение, если оно есть
                $cost = !empty($data['Cost in GBP']) ? (float)str_replace('$', '', $data['Cost in GBP']) : null;

                // Валидация стоимости и запаса
                if ($cost < 5 && (int)$data['Stock'] < 10) {
                    Log::warning("Пропущенная строка: цена менее 5 долларов и на складе менее 10 единиц - " . implode(',', $data));
                    $errorRows[] = implode(',', $data); // Сохраняем ошибочную строку
                    $errorCount++;
                    continue;
                }

                if ($cost > 1000) {
                    Log::warning("Пропущенная строка: цена превышает 1000 долларов - " . implode(',', $data));
                    $errorRows[] = implode(',', $data); // Сохраняем ошибочную строку
                    $errorCount++;
                    continue;
                }

                // Проверка формата Stock
                if (!is_numeric($data['Stock'])) {
                    Log::warning("Пропущенная строка: неверный формат Stock - " . implode(',', $data));
                    $errorRows[] = implode(',', $data); // Сохраняем ошибочную строку
                    $errorCount++;
                    continue;
                }

                // Устанавливаем значение dtmDiscontinued в зависимости от шестого параметра
                $dtmDiscontinued = (!empty($data['Discontinued']) && strtolower($data['Discontinued']) === 'yes') ? now() : null;

                // Подготовка данных для вставки
                $insertData = [
                    'strProductName' => substr($data['Product Name'], 0, 50), // Ограничение на длину
                    'strProductDesc' => substr($data['Product Description'], 0, 255), // Ограничение на длину
                    'strProductCode' => substr($data['Product Code'], 0, 10), // Ограничение на длину
                    'intStock' => (int)$data['Stock'], // Запись 0 теперь возможна
                    'decimalCost' => $cost,
                    'dtmDiscontinued' => $dtmDiscontinued,
                    'dtmAdded' => now()
                ];

                // Проверка на тестовый режим
                if ($this->option('test')) {
                    Log::info("Тестовый режим: Данные для вставки (не будут сохранены): " . json_encode($insertData));
                    $successCount++; // Успех в тестовом режиме
                } else {
                    // Добавляем данные в базу
                    try {
                        DB::table('tblProductData')->insert($insertData);
                        $successCount++;
                    } catch (\Exception $e) {
                        Log::error("Ошибка при вставке: " . $e->getMessage());
                        $errorRows[] = implode(',', $data); // Сохраняем ошибочную строку
                        $errorCount++;
                    }
                }
            }

            fclose($handle);

            // Отчет о процессе
            $this->info("Обработано строк: $processedCount");
            $this->info("Успешно импортировано: $successCount");
            $this->info("Ошибки: $errorCount");

            // Выводим строки с ошибками
            if ($errorCount > 0) {
                $this->info("Ошибочные строки:");
                foreach ($errorRows as $errorRow) {
                    $this->error($errorRow);
                }
            }
        } else {
            $this->error('Не удалось открыть файл.');
        }
    }
}


