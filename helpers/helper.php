<?php
/*
Author
*/

function is_list(array $array): bool
{
    $expectedKey = 0;
    foreach ($array as $i => $_) {
        if ($i !== $expectedKey) { return false; }
        $expectedKey++;
    }
    return true;
}