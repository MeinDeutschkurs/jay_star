<?php
    function j_memo_set($keypath, $value, $separator = '/') {
        return j_array_set($GLOBALS['_MEMOIZER'], $keypath, $value, $separator);
    } 