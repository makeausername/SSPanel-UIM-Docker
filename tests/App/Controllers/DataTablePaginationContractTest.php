<?php

declare(strict_types=1);

namespace App\Controllers;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DataTablePaginationContractTest extends TestCase
{
    #[DataProvider('paginatedTables')]
    public function testLargeTablesUseMatchingServerSidePaginationContracts(
        string $controller,
        string $template,
        string $responseKey
    ): void {
        $root = dirname(__DIR__, 3);
        $controllerSource = file_get_contents($root . '/' . $controller);
        $templateSource = file_get_contents($root . '/' . $template);

        $this->assertIsString($controllerSource);
        $this->assertIsString($templateSource);
        $this->assertStringContainsString('DataTableRequest::from(', $controllerSource);
        $this->assertStringContainsString('->paginate(', $controllerSource);
        $this->assertStringContainsString("'recordsTotal' =>", $controllerSource);
        $this->assertStringContainsString("'recordsFiltered' =>", $controllerSource);
        $this->assertStringContainsString('tableConfig.serverSide = true;', $templateSource);
        $this->assertStringContainsString("dataSrc: '{$responseKey}.data'", $templateSource);
    }

    public static function paginatedTables(): array
    {
        return [
            ['src/Controllers/Admin/UserController.php', 'resources/views/tabler/admin/user/index.tpl', 'users'],
            ['src/Controllers/Admin/InvoiceController.php', 'resources/views/tabler/admin/invoice/index.tpl', 'invoices'],
            ['src/Controllers/Admin/OrderController.php', 'resources/views/tabler/admin/order/index.tpl', 'orders'],
            ['src/Controllers/Admin/GiftCardController.php', 'resources/views/tabler/admin/giftcard.tpl', 'giftcards'],
            ['src/Controllers/Admin/CouponController.php', 'resources/views/tabler/admin/coupon.tpl', 'coupons'],
            ['src/Controllers/Admin/TicketController.php', 'resources/views/tabler/admin/ticket/index.tpl', 'tickets'],
            ['src/Controllers/Admin/NodeController.php', 'resources/views/tabler/admin/node/index.tpl', 'nodes'],
            ['src/Controllers/Admin/ProductController.php', 'resources/views/tabler/admin/product/index.tpl', 'products'],
            ['src/Controllers/Admin/AnnController.php', 'resources/views/tabler/admin/announcement/index.tpl', 'anns'],
            ['src/Controllers/Admin/DocsController.php', 'resources/views/tabler/admin/docs/index.tpl', 'docs'],
            ['src/Controllers/Admin/MoneyLogController.php', 'resources/views/tabler/admin/log/money.tpl', 'money_logs'],
            ['src/Controllers/Admin/PaylistController.php', 'resources/views/tabler/admin/log/gateway.tpl', 'paylists'],
            ['src/Controllers/Admin/PaybackController.php', 'resources/views/tabler/admin/log/payback.tpl', 'paybacks'],
            ['src/Controllers/User/InvoiceController.php', 'resources/views/tabler/user/invoice/index.tpl', 'invoices'],
            ['src/Controllers/User/OrderController.php', 'resources/views/tabler/user/order/index.tpl', 'orders'],
        ];
    }
}
