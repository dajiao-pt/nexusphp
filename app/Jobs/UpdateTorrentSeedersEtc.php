<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\User;
use App\Repositories\CleanupRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nexus\Database\NexusDB;

class UpdateTorrentSeedersEtc implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $beginTorrentId;

    private int $endTorrentId;

    private string $requestId;

    private ?string $idStr = null;

    private string $idRedisKey;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $beginTorrentId, int $endTorrentId, string $idStr, string $idRedisKey, string $requestId = '')
    {
        $this->beginTorrentId = $beginTorrentId;
        $this->endTorrentId = $endTorrentId;
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
        return now()->addSeconds(Setting::get('main.autoclean_interval_three'));
    }

    public $tries = 1;

    public $timeout = 1800;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $beginTimestamp = time();
        $logPrefix = sprintf("[CLEANUP_CLI_UPDATE_TORRENT_SEEDERS_ETC_HANDLE_JOB], commonRequestId: %s, beginTorrentId: %s, endTorrentId: %s", $this->requestId, $this->beginTorrentId, $this->endTorrentId);

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
        $torrentIdArr = explode(",", $idStr);
        //批量取，简单化
        $torrents = array();
        $res = sql_query("SELECT torrent, seeder, COUNT(*) AS c FROM peers GROUP BY torrent, seeder where torrent in ($idStr)");
        while ($row = mysql_fetch_assoc($res)) {
            if ($row["seeder"] == "yes")
            $key = "seeders";
            else
            $key = "leechers";
            $torrents[$row["torrent"]][$key] = $row["c"];
        }

        $res = sql_query("SELECT torrent, COUNT(*) AS c FROM comments GROUP BY torrent where torrent in ($idStr)");
        while ($row = mysql_fetch_assoc($res)) {
            $torrents[$row["torrent"]]["comments"] = $row["c"];
        }
        $seedersUpdates = $leechersUpdates = $commentsUpdates = [];
        foreach ($torrentIdArr as $id) {
            $seedersUpdates[] = sprintf("when %d then %d", $id, $torrents[$id]["seeders"] ?? 0);
            $leechersUpdates[] = sprintf("when %d then %d", $id, $torrents[$id]["leechers"] ?? 0);
            $commentsUpdates[] = sprintf("when %d then %d", $id, $torrents[$id]["comments"] ?? 0);
        }
        $sql = sprintf(
            "update torrents set seeders = case id %s end, leechers = case id %s end, comments = case id %s end where id in (%s)",
            implode(" ", $seedersUpdates), implode(" ", $leechersUpdates), implode(" ", $commentsUpdates), $idStr
        );
        $result = sql_query($sql);
        if ($delIdRedisKey) {
            NexusDB::cache_del($this->idRedisKey);
        }
        $costTime = time() - $beginTimestamp;
        do_log(sprintf(
            "$logPrefix, [DONE], update torrent count: %s, result: %s, cost time: %s seconds, sql: %s",
            count($torrentIdArr), var_export($result, true), $costTime, $sql
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
