<?php
namespace App\Repositories;

use App\Exceptions\NexusException;
use App\Models\BonusLogs;
use App\Models\HitAndRun;
use App\Models\Invite;
use App\Models\Medal;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserMedal;
use App\Models\UserMeta;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Nexus\Database\NexusDB;

class BonusRepository extends BaseRepository
{
    public function consumeToCancelHitAndRun($uid, $hitAndRunId): bool
    {
        if (!HitAndRun::getIsEnabled()) {
            throw new \LogicException("H&R not enabled.");
        }
        $user = User::query()->findOrFail($uid);
        $hitAndRun = HitAndRun::query()->findOrFail($hitAndRunId);
        if ($hitAndRun->uid != $uid) {
            throw new \LogicException("H&R: $hitAndRunId not belongs to user: $uid.");
        }
        if ($hitAndRun->status == HitAndRun::STATUS_PARDONED) {
            throw new \LogicException("H&R: $hitAndRunId already pardoned.");
        }
        $requireBonus = BonusLogs::getBonusForCancelHitAndRun();
        NexusDB::transaction(function () use ($user, $hitAndRun, $requireBonus) {
            $comment = nexus_trans('hr.bonus_cancel_comment', [
                'bonus' => $requireBonus,
            ], $user->locale);
            do_log("comment: $comment");

            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_CANCEL_HIT_AND_RUN, "$comment(H&R ID: {$hitAndRun->id})");

            $hitAndRun->update([
                'status' => HitAndRun::STATUS_PARDONED,
                'comment' => NexusDB::raw("if(comment = '', '$comment', concat_ws('\n', '$comment', comment))"),
            ]);
        });

        return true;

    }


    public function consumeToBuyMedal($uid, $medalId): bool
    {
        $user = User::query()->findOrFail($uid);
        $medal = Medal::query()->findOrFail($medalId);
        $exists = $user->valid_medals()->where('medal_id', $medalId)->exists();
        do_log(last_query());
        if ($exists) {
            throw new \LogicException("user: $uid already own this medal: $medalId.");
        }
        if ($medal->get_type != Medal::GET_TYPE_EXCHANGE) {
            throw new \LogicException("This medal can not be buy.");
        }
        if ($medal->inventory !== null && $medal->inventory <= 0) {
            throw new \LogicException("Inventory empty.");
        }
        $now = now();
        if ($medal->sale_begin_time && $medal->sale_begin_time->gt($now)) {
            throw new \LogicException("Before sale begin time.");
        }
        if ($medal->sale_end_time && $medal->sale_end_time->lt($now)) {
            throw new \LogicException("After sale end time.");
        }
        $requireBonus = $medal->price;
        NexusDB::transaction(function () use ($user, $medal, $requireBonus) {
            $comment = nexus_trans('bonus.comment_buy_medal', [
                'bonus' => $requireBonus,
                'medal_name' => $medal->name,
            ], $user->locale);
            do_log("comment: $comment");
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_MEDAL, "$comment(medal ID: {$medal->id})");
            $expireAt = null;
            if ($medal->duration > 0) {
                $expireAt = Carbon::now()->addDays($medal->duration)->toDateTimeString();
            }
            $user->medals()->attach([$medal->id => ['expire_at' => $expireAt, 'status' => UserMedal::STATUS_NOT_WEARING]]);
            if ($medal->inventory !== null) {
                $affectedRows = NexusDB::table('medals')
                    ->where('id', $medal->id)
                    ->where('inventory', $medal->inventory)
                    ->decrement('inventory')
                ;
                if ($affectedRows != 1) {
                    throw new \RuntimeException("Decrement medal({$medal->id}) inventory affected rows != 1($affectedRows)");
                }
            }

        });

        return true;

    }

    public function consumeToBuyAttendanceCard($uid): bool
    {
        $user = User::query()->findOrFail($uid);
        $requireBonus = BonusLogs::getBonusForBuyAttendanceCard();
        NexusDB::transaction(function () use ($user, $requireBonus) {
            $comment = nexus_trans('bonus.comment_buy_attendance_card', [
                'bonus' => $requireBonus,
            ], $user->locale);
            do_log("comment: $comment");
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_ATTENDANCE_CARD, $comment);
            User::query()->where('id', $user->id)->increment('attendance_card');
        });

        return true;

    }


    public function consumeToBuyTemporaryInvite($uid, $count = 1): bool
    {
        $requireBonus = BonusLogs::getBonusForBuyTemporaryInvite();
        if ($requireBonus <= 0) {
            throw new \RuntimeException("Temporary invite require bonus <= 0 !");
        }
        $user = User::query()->findOrFail($uid);
        $toolRep = new ToolRepository();
        $hashArr = $toolRep->generateUniqueInviteHash([], $count, $count);
        NexusDB::transaction(function () use ($user, $requireBonus, $hashArr) {
            $comment = nexus_trans('bonus.comment_buy_temporary_invite', [
                'bonus' => $requireBonus,
                'count' => count($hashArr)
            ], $user->locale);
            do_log("comment: $comment");
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_TEMPORARY_INVITE, $comment);
            $invites = [];
            foreach ($hashArr as $hash) {
                $invites[] = [
                    'inviter' => $user->id,
                    'invitee' => '',
                    'hash' => $hash,
                    'valid' => 0,
                    'expired_at' => Carbon::now()->addDays(Invite::TEMPORARY_INVITE_VALID_DAYS),
                    'created_at' => Carbon::now(),
                ];
            }
            Invite::query()->insert($invites);
        });

        return true;

    }

    public function consumeToBuyRainbowId($uid, $duration = 30): bool
    {
        $user = User::query()->findOrFail($uid);
        $requireBonus = BonusLogs::getBonusForBuyRainbowId();
        NexusDB::transaction(function () use ($user, $requireBonus, $duration) {
            $comment = nexus_trans('bonus.comment_buy_rainbow_id', [
                'bonus' => $requireBonus,
                'duration' => $duration,
            ], $user->locale);
            do_log("comment: $comment");
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_RAINBOW_ID, $comment);
            $metaData = [
                'meta_key' => UserMeta::META_KEY_PERSONALIZED_USERNAME,
                'duration' => $duration,
            ];
            $userRep = new UserRepository();
            $userRep->addMeta($user, $metaData, $metaData, false);
        });

        return true;

    }

    public function consumeToBuyChangeUsernameCard($uid): bool
    {
        $user = User::query()->findOrFail($uid);
        $requireBonus = BonusLogs::getBonusForBuyChangeUsernameCard();
        if (UserMeta::query()->where('uid', $uid)->where('meta_key', UserMeta::META_KEY_CHANGE_USERNAME)->exists()) {
            throw new NexusException("user already has change username card");
        }
        NexusDB::transaction(function () use ($user, $requireBonus) {
            $comment = nexus_trans('bonus.comment_buy_change_username_card', [
                'bonus' => $requireBonus,
            ], $user->locale);
            do_log("comment: $comment");
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_CHANGE_USERNAME_CARD, $comment);
            $metaData = [
                'meta_key' => UserMeta::META_KEY_CHANGE_USERNAME,
            ];
            $userRep = new UserRepository();
            $userRep->addMeta($user, $metaData, $metaData, false);
        });

        return true;

    }

    public function consumeUserBonus($user, $requireBonus, $logBusinessType, $logComment = '', array $userUpdates = [])
    {
        if (!isset(BonusLogs::$businessTypes[$logBusinessType])) {
            throw new \InvalidArgumentException("Invalid logBusinessType: $logBusinessType");
        }
        if (isset($userUpdates['seedbonus']) || isset($userUpdates['bonuscomment'])) {
            throw new \InvalidArgumentException("Not support update seedbonus or bonuscomment");
        }
        if ($requireBonus <= 0) {
            return;
        }
        $user = $this->getUser($user);
        if ($user->seedbonus < $requireBonus) {
            do_log("user: {$user->id}, bonus: {$user->seedbonus} < requireBonus: $requireBonus", 'error');
            throw new \LogicException("User bonus not enough.");
        }
        NexusDB::transaction(function () use ($user, $requireBonus, $logBusinessType, $logComment, $userUpdates) {
            $logComment = addslashes($logComment);
            $bonusComment = date('Y-m-d') . " - $logComment";
            $oldUserBonus = $user->seedbonus;
            $newUserBonus = bcsub($oldUserBonus, $requireBonus);
            $log = "user: {$user->id}, requireBonus: $requireBonus, oldUserBonus: $oldUserBonus, newUserBonus: $newUserBonus, logBusinessType: $logBusinessType, logComment: $logComment";
            do_log($log);
            $userUpdates['seedbonus'] = $newUserBonus;
            $userUpdates['bonuscomment'] = NexusDB::raw("if(bonuscomment = '', '$bonusComment', concat_ws('\n', '$bonusComment', bonuscomment))");
            $affectedRows = NexusDB::table($user->getTable())
                ->where('id', $user->id)
                ->where('seedbonus', $oldUserBonus)
                ->update($userUpdates);
            if ($affectedRows != 1) {
                do_log("update user seedbonus affected rows != 1, query: " . last_query(), 'error');
                throw new \RuntimeException("Update user seedbonus fail.");
            }
            $bonusLog = [
                'business_type' => $logBusinessType,
                'uid' => $user->id,
                'old_total_value' => $oldUserBonus,
                'value' => $requireBonus,
                'new_total_value' => $newUserBonus,
                'comment' => sprintf('[%s] %s', BonusLogs::$businessTypes[$logBusinessType]['text'], $logComment),
            ];
            BonusLogs::query()->insert($bonusLog);
            do_log("bonusLog: " . nexus_json_encode($bonusLog));
        });
    }


}
