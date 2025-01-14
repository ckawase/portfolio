<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Services\Calender\EventTime;
use App\Http\Requests\EventRequest;
use App\Http\Requests\UpdatedEventRequest;

class EventController extends Controller
{
    public function index()
    {
        //終日のイベントを取得
        $events = Event::select('title', 'startDate', 'endDate')
        ->orderBy('startDate', 'asc')  // startDate で昇順に並べ替え
        ->orderBy('startTime', 'asc')  // 同じ startDate の場合は startTime で昇順に並べ替え
        ->get();

        return view('works.events.index',['events' => $events]);
    }

    public function schedule($date)
    {
        $allDayEvents = Event::select('title', 'startDate', 'endDate', 'body', 'id')
        ->where('isAllDay', true)  // isAllDay = 1 の条件
        ->where(function($query) use ($date) {
            $query->whereDate('startDate', '<=', $date)
                  ->whereDate('endDate', '>=', $date);
                })
        ->orderBy('startDate')
        ->get();

        $allDayEvents = EventTime::convertDateToJapaneseFormat($allDayEvents);

        $timedEvents = Event::select('title',DB::raw("DATE_FORMAT(startTime, '%I:%i %p') AS startTime"),DB::raw("DATE_FORMAT(endTime, '%I:%i %p') AS endTime"),'id')
        ->where('isAllDay', false)  // isAllDay = 0 の条件
        ->where(function($query) use ($date) {
            $query->whereDate('startDate', '<=', $date)
                  ->whereDate('endDate', '>=', $date);
                })
        ->orderByRaw("DATE_FORMAT(startTime, '%H:%i')")
        ->get();

        $timedEvents = EventTime::convertTimePeriod($timedEvents);//時間を○○：○○の表記から午前〇時の表記に変更する

        $fullDate = EventTime::getFullDate($date);//スケジュールページに表示する〇月〇日（曜日）の形式になっている日付を取得


        // ビューにデータを渡す
        return view('works.events.schedule', ['allDayEvents' => $allDayEvents,'timedEvents' => $timedEvents, 'fullDate' => $fullDate, 'date' => $date]);
    }



    public function show(Event $event)
    {
        $event = Event::findOrFail($event->id); // IDから該当のイベントを取得

        $event = EventTime::convertSingleEventTimePeriod($event);//時間部分の表示を日本語に変更
        $startDate = $event->startDate;
        $endDate = $event->endDate;

        $startDate = EventTime::getFullDate($startDate);
        $endDate = EventTime::getFullDate($endDate);

        return view('works.events.show', ['event' => $event, 'startDate' => $startDate, 'endDate' => $endDate]);
    }

    public function create($date)
    {
        return view('works.events.create', ['date' => $date]);
    }

    public function store(EventRequest $request)
    {
        // チェックボックスの値を取得
        $isAllDay = $request->has('isAllDay'); // チェックが入っていれば true、入っていなければ false

        //バリデーション済みのデータを取得
        $validated = $request->validated();

        //新しいイベントを作成し、バリデーション済みデータを保存
        $event = Event::create($validated);

        $event->isAllDay = $isAllDay;//isAllDayをtrue,falseで登録
        $event->save();

        return to_route('events.schedule', ['date' => $event->startDate]);
    }

    public function edit(Event $event)
    {
        return view('works.events.edit', ['event' => $event]);
    }

    public function update(UpdatedEventRequest $request, Event $event)
    {
        // チェックボックスの値を取得
        $isAllDay = $request->has('isAllDay') ? true : false; // チェックが入っていれば true、入っていなければ false
        $event = Event::findOrFail($event->id);

        // バリデーション済みのデータを取得
        $updateData = $request->validated();
        $event->update($updateData);

        $event->isAllDay = $isAllDay;
        $event->save();

       return to_route('events.schedule', ['date' => $event->startDate]);
    }



    public function destroy(Event $event)
    {
        $date = $event->startDate;
        $event->delete();

        return to_route('events.schedule', ['date' => $date]);
    }
}
