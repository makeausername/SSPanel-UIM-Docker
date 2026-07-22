<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\InviteCode;
use App\Models\InviteSubscriptionReward;
use App\Utils\Tools;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class InviteController extends BaseController
{
    /**
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $code = (new InviteCode())->where('user_id', $this->user->id)->first()?->code;

        if ($code === null) {
            $code = (new InviteCode())->add($this->user->id);
        }

        $rewards = (new InviteSubscriptionReward())->where('inviter_user_id', $this->user->id)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($rewards as $reward) {
            $reward->created_at = Tools::toDateTime($reward->create_time);
        }

        $appliedDays = (int) (new InviteSubscriptionReward())
            ->where('inviter_user_id', $this->user->id)
            ->where('status', 'applied')
            ->sum('reward_days');
        $pendingDays = (int) (new InviteSubscriptionReward())
            ->where('inviter_user_id', $this->user->id)
            ->where('status', 'pending')
            ->sum('reward_days');

        $invite_url = $_ENV['baseUrl'] . '/auth/register?code=' . $code;

        return $response->write(
            $this->view()
                ->assign('rewards', $rewards)
                ->assign('invite_url', $invite_url)
                ->assign('applied_days', $appliedDays)
                ->assign('pending_days', $pendingDays)
                ->fetch('user/invite.tpl')
        );
    }

    public function reset(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $user = $this->user;
        $user->removeInvite();
        $code = (new InviteCode())->add($user->id);

        return $response->withJson([
            'ret' => 1,
            'msg' => '重置成功',
            'data' => [
                'invite-url' => $_ENV['baseUrl'] . '/auth/register?code=' . $code,
            ],
        ]);
    }
}
