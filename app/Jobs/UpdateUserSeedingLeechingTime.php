<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Repositories\CleanupRepository;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nexus\Database\NexusDB;

class UpdateUserSeedingLeechingTime implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $beginUid;

    private int $endUid;

    private string $requestId;

    private ?string $idStr = null;

    private string $idRedisKey;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $beginUid, int $endUid, string $idStr, string $idRedisKey, string $requestId = '')
    {
        $this->beginUid = $beginUid;
        $this->endUid = $endUid;
        $this->idStr = $idStr;
        $this->idRedisKey = $idRedisKey;
        $this->requestId = $requestId;
    }

    /**
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime
     */
    public function retryUntil()
    {
        return now()->addSeconds(Setting::get('main.autoclean_interval_four'));
    }

    public $tries = 1;

    public $timeout = 3600;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $beginTimestamp = time();
        $logPrefix = sprintf("[CLEANUP_CLI_UPDATE_SEEDING_LEECHING_TIME_HANDLE_JOB], commonRequestId: %s, beginUid: %s, endUid: %s", $this->requestId, $this->beginUid, $this->endUid);

        $idStr = $this->idStr;
        $delIdRedisKey = false;
        if (empty($idStr) && !empty($this->idRedisKey)) {
            $delIdRedisKey = true;
            $idStr = NexusDB::cache_get($this->idRedisKey);
        }
        if (empty($idStr)) {
            do_log("$logPrefix, no idStr or idRedisKey", "error");
            return;
        }
        //批量取，简单化
        $res = sql_query("select userid, sum(seedtime) as seedtime_sum, sum(leechtime) as leechtime_sum from snatched group by userid where userid in ($idStr)");
        $seedtimeUpdates = $leechTimeUpdates = [];
        $nowStr = now()->toDateTimeString();
        $count = 0;
        while ($row = mysql_fetch_assoc($res)) {
            $count++;
            $seedtimeUpdates = sprintf("when %d then %d", $row['userid'], $row['seedtime_sum'] ?? 0);
            $leechTimeUpdates = sprintf("when %d then %d", $row['userid'], $row['leechtime_sum'] ?? 0);
        }
        $sql = sprintf(
            "update users set seedtime = case id %s end, leechtime = case id %s end, seed_time_updated_at = '%s' where id in (%s)",
            implode(" ", $seedtimeUpdates), implode(" ", $leechTimeUpdates), $nowStr, $idStr
        );
        $result = sql_query($sql);
        if ($delIdRedisKey) {
            NexusDB::cache_del($this->idRedisKey);
        }
        $costTime = time() - $beginTimestamp;
        do_log(sprintf(
            "$logPrefix, [DONE], update user count: %s, result: %s, cost time: %s seconds, sql: %s",
            $count, var_export($result, true), $costTime, $sql
        ));
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        do_log("failed: " . $exception->getMessage() . $exception->getTraceAsString(), 'error');
    }
}
