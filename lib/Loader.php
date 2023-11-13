<?php

namespace Slavytuch\WorkTimeCheck;

use BugrovWeb\YandexTracker\Api\Tracker;
use BugrovWeb\YandexTracker\Exceptions\TrackerBadMethodException;
use BugrovWeb\YandexTracker\Exceptions\TrackerBadParamsException;
use BugrovWeb\YandexTracker\Exceptions\TrackerBadResponseException;
use Carbon\Carbon;
use DateInterval;
use DateTime;
use Exception;
use Slavytuch\WorkTimeCheck\DTO\MyEntryDTO;
use Slavytuch\WorkTimeCheck\DTO\TrackerEntryDTO;

class Loader
{
    private Tracker $api;

    public function __construct()
    {
        $dotenv = \Dotenv\Dotenv::createImmutable('../');
        $dotenv->load();


        $token = $_ENV['TRACKER_TOKEN'];
        $orgId = $_ENV['TRACKER_ORGANIZATION_ID'];

        $this->api = new Tracker($token, $orgId);
    }

    /**
     * @param DateTime $from
     * @return array
     * @throws TrackerBadMethodException
     * @throws TrackerBadParamsException
     * @throws TrackerBadResponseException
     */
    protected function getTrackerEntries(\DateTime $from): array
    {
        $me = $this->api->user()->getInfo()->send()->getResponse();
        $dateEntries = $this->api->worklog()->get()->createdBy($me['login'])->createdAt([
            'from' => $from->format('Y-m-d'),
        ])->send()->getResponse();
        $trackerEntries = [];
        foreach ($dateEntries as $entry) {
            $createdAt = date('j.m', strtotime($entry['createdAt']));
            $trackerEntries[$createdAt][] = new TrackerEntryDTO(
                task: $entry['issue']['key'],
                time: new DateInterval(preg_replace('/\.[0-9]+/', '', $entry['duration'])),
                date: $createdAt
            );
        }

        return $trackerEntries;
    }

    /**
     * @param DateTime $from
     * @return array
     * @throws Exception
     */
    protected function getMyEntries(\DateTime $from)
    {
        if ($from->diff(Carbon::now())->invert) {
            throw new Exception('Передаваемая дата больше текущей');
        }
        $myFile = fopen('рабочее время.txt', 'r');

        if (!$myFile) {
            throw new Exception('Не могу открыть файл');
        }

        $myEntries = [];
        $date = $start = $from->format('d.m');

        $startFound = false;
        while (!feof($myFile)) {
            $row = fgets($myFile);
            if (!$row || $row === PHP_EOL) {
                continue;
            }

            if ($row === $start . PHP_EOL) {
                $date = trim($start);
                $startFound = true;
                continue;
            }

            if (!$startFound) {
                continue;
            }

            $parts = explode(' - ', $row);
            if (count($parts) < 3) {
                $date = trim($parts[0]);

                continue;
            }

            [$timeStart, $timeEnd, $message] = $parts;

            $message = str_replace(PHP_EOL, '', $message);

            if ($message === 'обед') {
                continue;
            }

            if ($message === 'нераспред') {
                $message = 'INT-718';
            }

            $myEntries[$date][] = new MyEntryDTO(
                task: $message,
                time: (new DateTime($timeStart))->diff(new DateTime($timeEnd)),
                date: $date
            );
        }

        if (!$startFound) {
            $from->add(DateInterval::createFromDateString('1 day'));
            return $this->getMyEntries($from);
        }

        return $myEntries;
    }

    public function calculate(): array
    {
        $from = \Carbon\Carbon::now()->subDays(4)->startOfDay();

        $myEntries = $this->getMyEntries($from);
        $trackerEntries = $this->getTrackerEntries($from);
        $result = [];

        foreach (array_merge(array_keys($myEntries), array_keys($trackerEntries)) as $date) {
            if(!isset($myEntries[$date])) {
                $result[$date] = [
                    'missingInFile' => $this->sumOfEntries($trackerEntries[$date]),
                    'missingInTracker' => [],
                    'timeDifference' => [],
                ];
                continue;
            }

            $myTodayEntries = $this->sumOfEntries($myEntries[$date]);
            if(!isset($trackerEntries[$date])) {
                $result[$date] = [
                    'missingInFile' => [],
                    'missingInTracker' => $myTodayEntries,
                    'timeDifference' => [],
                ];
                continue;
            }

            $trackerTodayEntries = $this->sumOfEntries($trackerEntries[$date]);

            $missingInTracker = array_diff(array_keys($myTodayEntries), array_keys($trackerTodayEntries));

            $missingInTrackerResult = [];
            foreach ($missingInTracker as $key) {
                $missingInTrackerResult[$key] = $myTodayEntries[$key];
            }

            $missingInFile = array_diff(array_keys($trackerTodayEntries), array_keys($myTodayEntries));

            $missingInFileResult = [];
            foreach ($missingInFile as $key) {
                $missingInFileResult[$key] = $trackerTodayEntries[$key];
            }

            $timeDifference = [];

            foreach ($myTodayEntries as $task => $myTodayEntry) {
                if(in_array($task, $missingInFile) || in_array($task, $missingInTracker)) {
                    continue;
                }

                $difference = $myTodayEntry - $trackerTodayEntries[$task];

                if($difference < 3) {
                    continue;
                }

                $timeDifference[$task] = $myTodayEntry - $trackerTodayEntries[$task];
            }

            $result[$date] = [
                'missingInFile' => $missingInFileResult,
                'missingInTracker' => $missingInTrackerResult,
                'timeDifference' => $timeDifference,
            ];
        }

        return $result;
    }

    protected function minutes(DateInterval $interval)
    {
        return $interval->i + 60 * $interval->h;
    }

    protected function sumOfEntries(array $entries)
    {
        $result = [];
        foreach ($entries as $entry) {
            if(!isset($result[$entry->task])) {
                $result[$entry->task] = 0;
            }

            $result[$entry->task] += $this->minutes($entry->time);
        }

        return $result;
    }
}