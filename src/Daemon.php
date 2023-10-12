<?php

namespace App;

class Daemon
{
    protected static $callbacks = [];
    protected static $precb = null;
    /**
     * @var int microseconds to sleep for the event loop
     */
    static $us = 10000;
    /**
     * @var bool stop daemon when true
     */
    static $stop = false;
    /**
     * @var int current loop time, changing this might break the daemon
     */
    static $time = null;
    /**
     * @var int last loop time, changing this might break the daemon
     */
    static $last_time = null;

    private static function new()
    {
        return static::$time != static::$last_time;
    }

    /**
     * Add callback to daemon
     */
    static function create(...$callback)
    {
        foreach ($callback as $cb) {
            static::$callbacks[] = Callback::make($cb);
        }
    }

    private static function exec($callbacks)
    {
        foreach ($callbacks as $callback) {
            yield call_user_func_array($callback, []);
        }
    }

    /**
     * Removes all callbacks from daemon
     */
    static function clear()
    {
        static::$callbacks = [];
    }

    /**
     * Creates a callback that will be called before calling all callbacks
     */
    static function precallback($callback)
    {
        static::$precb = Callback::make($callback);
    }

    private static function call_precb()
    {
        if (static::$precb) {
            call_user_func_array(static::$precb, []);
        }
    }

    /**
     * Initialize daemon
     */
    static function run($max_loop = -1)
    {
        static::$stop = false;
        static::$time = time();
        static::$last_time = static::$time;

        while (!static::$stop) {
            static::$time = intval(date('U'));
            if (static::new()) {
                if ($max_loop >= 0) {
                    if ($max_loop == 0) {
                        return;
                    }

                    $max_loop--;
                }

                static::call_precb();
                $callbacks = static::$callbacks;
                foreach ($callbacks as $callback) {
                    call_user_func_array($callback, []);
                }
            }

            static::$last_time = static::$time;
            usleep(static::$us);
        }
    }
}