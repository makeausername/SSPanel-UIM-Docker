<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\GiftCard;
use App\Services\AdminPermissionService;
use App\Services\DataTableRequest;
use App\Services\GiftCardService;
use App\Utils\Tools;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use function filter_var;
use function htmlspecialchars;
use function implode;
use function in_array;
use const PHP_EOL;
use const FILTER_VALIDATE_INT;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

final class GiftCardController extends BaseController
{
    private static array $details = [
        'field' => [
            'op' => '操作',
            'id' => '礼品卡ID',
            'card' => '卡号',
            'balance' => '面值',
            'create_time' => '创建时间',
            'status' => '使用状态',
            'use_time' => '使用时间',
            'use_user' => '使用用户',
        ],
        'create_dialog' => [
            [
                'id' => 'card_number',
                'info' => '创建数量',
                'type' => 'input',
                'placeholder' => '',
            ],
            [
                'id' => 'card_value',
                'info' => '礼品卡面值',
                'type' => 'input',
                'placeholder' => '',
            ],
            [
                'id' => 'card_length',
                'info' => '礼品卡长度',
                'type' => 'select',
                'select' => [
                    '12' => '12位',
                    '18' => '18位',
                    '24' => '24位',
                    '30' => '30位',
                    '36' => '36位',
                ],
            ],
        ],
    ];

    /**
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write(
            $this->view()
                ->assign('details', self::$details)
                ->fetch('admin/giftcard.tpl')
        );
    }

    public function add(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $cardNumber = filter_var($request->getParam('card_number'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => GiftCardService::MAX_BATCH_SIZE],
        ]);
        $cardValue = filter_var($request->getParam('card_value'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => GiftCardService::MAX_VALUE],
        ]);
        $cardLength = filter_var($request->getParam('card_length'), FILTER_VALIDATE_INT);

        if ($cardNumber === false) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '单次生成数量必须为 1-' . GiftCardService::MAX_BATCH_SIZE . ' 的整数',
            ]);
        }

        if ($cardValue === false) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '礼品卡面值必须为 1-' . GiftCardService::MAX_VALUE . ' 的整数',
            ]);
        }

        if ($cardLength === false || ! in_array($cardLength, GiftCardService::ALLOWED_LENGTHS, true)) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '礼品卡长度无效',
            ]);
        }

        try {
            $codes = GiftCardService::createBatch($cardNumber, $cardValue, $cardLength);
        } catch (\Throwable) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '礼品卡生成失败，请稍后重试',
            ]);
        }

        return $response->withJson([
            'ret' => 1,
            'msg' => '添加成功' . PHP_EOL . implode(PHP_EOL, $codes),
        ]);
    }

    public function delete(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $card_id = $args['id'];
        $giftCard = (new GiftCard())->find($card_id);

        if ($giftCard === null) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '礼品卡不存在',
            ]);
        }

        $giftCard->delete();

        return $response->withJson([
            'ret' => 1,
            'msg' => '删除成功',
        ]);
    }

    public function ajax(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $table = DataTableRequest::from(
            $request,
            ['id', 'card', 'balance', 'create_time', 'status', 'use_time', 'use_user'],
            'id'
        );
        $query = GiftCard::query();
        $total = (new GiftCard())->count();
        if ($table->search !== '') {
            $query->where(static function ($query) use ($table): void {
                $query->where('id', $table->search)
                    ->orWhere('card', 'LIKE', "%{$table->search}%")
                    ->orWhere('use_user', $table->search);
            });
        }
        $filtered = $query->count();
        $query->orderBy($table->orderBy, $table->orderDirection);
        if ($table->orderBy !== 'id') {
            $query->orderBy('id', 'desc');
        }
        $giftcards = $query->paginate($table->length, '*', '', $table->page);
        $canMutate = AdminPermissionService::allows($this->user, 'DELETE', '/admin/giftcard/1');
        $canViewCodes = AdminPermissionService::role($this->user) !== 'read_only';

        $giftcards->getCollection()->transform(static function (GiftCard $giftcard) use ($canMutate, $canViewCodes): array {
            $giftcard->op = $canMutate ? '<button class="btn btn-red" id="delete-gift-card-' . $giftcard->id . '"
        onclick="deleteGiftCard(' . $giftcard->id . ')">删除</button>' : '';
            $giftcard->card = $canViewCodes
                ? htmlspecialchars((string) $giftcard->card, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                : '••••••••';
            $giftcard->status = $giftcard->status();
            $giftcard->create_time = Tools::toDateTime((int) $giftcard->create_time);
            $giftcard->use_time = Tools::toDateTime((int) $giftcard->use_time);

            return $giftcard->only([
                'op',
                'id',
                'card',
                'balance',
                'create_time',
                'status',
                'use_time',
                'use_user',
            ]);
        });

        return $response->withJson([
            'draw' => $table->draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'giftcards' => $giftcards,
        ]);
    }
}
