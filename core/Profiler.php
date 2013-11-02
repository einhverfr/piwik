<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik
 * @package Piwik
 */
namespace Piwik;

use Piwik\Db\Factory;

/**
 * Class Profiler helps with measuring memory, and profiling the database.
 * To enable set in your config.ini.php
 *   [Debug]
 *   enable_sql_profiler = 1
 *
 *   [log]
 *   log_writers[] = file
 *   log_level=debug
 *
 * @package Piwik
 */
class Profiler
{
    /**
     * Returns memory usage
     *
     * @return string
     */
    public static function getMemoryUsage()
    {
        $memory = false;
        if (function_exists('xdebug_memory_usage')) {
            $memory = xdebug_memory_usage();
        } elseif (function_exists('memory_get_usage')) {
            $memory = memory_get_usage();
        }
        if ($memory === false) {
            return "Memory usage function not found.";
        }
        $usage = number_format(round($memory / 1024 / 1024, 2), 2);
        return "$usage Mb";
    }

    /**
     * Outputs SQL Profiling reports from Zend
     *
     * @throws \Exception
     */
    public static function displayDbProfileReport()
    {
        $profiler = Db::get()->getProfiler();

        if (!$profiler->getEnabled()) {
            throw new \Exception("To display the profiler you should enable enable_sql_profiler on your config/config.ini.php file");
        }

        $infoIndexedByQuery = array();
        foreach ($profiler->getQueryProfiles() as $query) {
            if (isset($infoIndexedByQuery[$query->getQuery()])) {
                $existing = $infoIndexedByQuery[$query->getQuery()];
            } else {
                $existing = array('count' => 0, 'sumTimeMs' => 0);
            }
            $new = array('count'     => $existing['count'] + 1,
                         'sumTimeMs' => $existing['count'] + $query->getElapsedSecs() * 1000);
            $infoIndexedByQuery[$query->getQuery()] = $new;
        }

        uasort($infoIndexedByQuery, 'self::sortTimeDesc');

        $str = '<hr /><strong>SQL Profiler</strong><hr /><strong>Summary</strong><br/>';
        $totalTime = $profiler->getTotalElapsedSecs();
        $queryCount = $profiler->getTotalNumQueries();
        $longestTime = 0;
        $longestQuery = null;
        foreach ($profiler->getQueryProfiles() as $query) {
            if ($query->getElapsedSecs() > $longestTime) {
                $longestTime = $query->getElapsedSecs();
                $longestQuery = $query->getQuery();
            }
        }
        $str .= 'Executed ' . $queryCount . ' queries in ' . round($totalTime, 3) . ' seconds';
        $str .= '(Average query length: ' . round($totalTime / $queryCount, 3) . ' seconds)';
        $str .= '<br />Queries per second: ' . round($queryCount / $totalTime, 1);
        $str .= '<br />Longest query length: ' . round($longestTime, 3) . " seconds (<code>$longestQuery</code>)";
        Log::debug($str);
        self::getSqlProfilingQueryBreakdownOutput($infoIndexedByQuery);
    }

    private static function maxSumMsFirst($a, $b)
    {
        return $a['sum_time_ms'] < $b['sum_time_ms'];
    }

    static private function sortTimeDesc($a, $b)
    {
        return $a['sumTimeMs'] < $b['sumTimeMs'];
    }

    /**
     * Print profiling report for the tracker
     *
     * @param \Piwik\Db $db Tracker database object (or null)
     */
    public static function displayDbTrackerProfile($db = null)
    {
        if (is_null($db)) {
            $db = Tracker::getDatabase();
        }

        $LogProfiling = Factory::getDAO('log_profiling', $db);
        $all = $LogProfiling->getAll();
        if ($all === false) {
            return;
        }
        uasort($all, 'self::maxSumMsFirst');

        $infoIndexedByQuery = array();
        foreach ($all as $infoQuery) {
            $query = $infoQuery['query'];
            $count = $infoQuery['count'];
            $sum_time_ms = $infoQuery['sum_time_ms'];
            $infoIndexedByQuery[$query] = array('count' => $count, 'sumTimeMs' => $sum_time_ms);
        }
        self::getSqlProfilingQueryBreakdownOutput($infoIndexedByQuery);
    }

    /**
     * Print number of queries and elapsed time
     */
    public static function printQueryCount()
    {
        $totalTime = self::getDbElapsedSecs();
        $queryCount = Profiler::getQueryCount();
        Log::debug(sprintf("Total queries = %d (total sql time = %.2fs)", $queryCount, $totalTime));
    }

    /**
     * Get total elapsed time (in seconds)
     *
     * @return int  elapsed time
     */
    public static function getDbElapsedSecs()
    {
        $profiler = Db::get()->getProfiler();
        return $profiler->getTotalElapsedSecs();
    }

    /**
     * Get total number of queries
     *
     * @return int  number of queries
     */
    public static function getQueryCount()
    {
        $profiler = Db::get()->getProfiler();
        return $profiler->getTotalNumQueries();
    }

    /**
     * Log a breakdown by query
     *
     * @param array $infoIndexedByQuery
     */
    static private function getSqlProfilingQueryBreakdownOutput($infoIndexedByQuery)
    {
        $output = '<hr /><strong>Breakdown by query</strong><br/>';
        foreach ($infoIndexedByQuery as $query => $queryInfo) {
            $timeMs = round($queryInfo['sumTimeMs'], 1);
            $count = $queryInfo['count'];
            $avgTimeString = '';
            if ($count > 1) {
                $avgTimeMs = $timeMs / $count;
                $avgTimeString = " (average = <b>" . round($avgTimeMs, 1) . "ms</b>)";
            }
            $query = preg_replace('/([\t\n\r ]+)/', ' ', $query);
            $output .= "Executed <b>$count</b> time" . ($count == 1 ? '' : 's') . " in <b>" . $timeMs . "ms</b> $avgTimeString <pre>\t$query</pre>";
        }
        Log::debug($output);
    }
}
