<?php


namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ImportProductDataTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_logs_warnings_for_invalid_data()
    {
        // Создаем временный CSV файл с ошибочными данными
        $csvData = "Product Code,Product Name,Product Description,Stock,Cost in GBP,Discontinued\n"
            . "P0001,TV,32” TV,10,399.99,\n" // Корректная строка
            . "P0002,CD Player,,20,50.12,\n" // Отсутствует Product Description
            . "P0003,VCR,Top notch VCR,0,4.00,\n" // Запас 0 и цена < 5
            . "P0004,Bluray Player,Watch it in HD,1,1100,\n" // Цена > 1000
            . ",Invalid Product,,20,50.12,\n"; // Пустой код продукта

        $filePath = storage_path('app/test_invalid.csv');
        file_put_contents($filePath, $csvData);

        // Перехватываем логирование
        Log::shouldReceive('warning')->times(4); // Теперь 4 предупреждения

        // Запускаем команду импорта
        $this->artisan('import:productdata ' . $filePath)
            ->expectsOutput('Обработано строк: 5') // Теперь 5 обработанных строк
            ->expectsOutput('Успешно импортировано: 1')
            ->expectsOutput('Ошибки: 4')
            ->assertExitCode(0);

        // Проверяем, что данные с ошибками не были импортированы
        $this->assertDatabaseMissing('tblProductData', ['strProductCode' => 'P0002']);
        $this->assertDatabaseMissing('tblProductData', ['strProductCode' => 'P0003']);
        $this->assertDatabaseMissing('tblProductData', ['strProductCode' => 'P0004']);
    }

    /** @test */
    public function it_imports_valid_data()
    {
        // Создаем временный CSV файл с корректными данными
        $csvData = "Product Code,Product Name,Product Description,Stock,Cost in GBP,Discontinued\n"
            . "P0001,TV,32” TV,10,399.99,\n" // Корректная строка
            . "P0002,CD Player,Plays CDs,20,50.12,\n"; // Корректная строка

        $filePath = storage_path('app/test_valid.csv');
        file_put_contents($filePath, $csvData);

        // Запускаем команду импорта
        $this->artisan('import:productdata ' . $filePath)
            ->expectsOutput('Обработано строк: 2') // 2 обработанные строки
            ->expectsOutput('Успешно импортировано: 2')
            ->expectsOutput('Ошибки: 0')
            ->assertExitCode(0);

        // Проверяем, что данные были импортированы
        $this->assertDatabaseHas('tblProductData', ['strProductCode' => 'P0001']);
        $this->assertDatabaseHas('tblProductData', ['strProductCode' => 'P0002']);
    }
}



