<?php
    function j_memo_get($keypath="", $separator = '/', $default = null) {
        return j_array_get($GLOBALS['_MEMOIZER'], $keypath, $separator, $default);
    }