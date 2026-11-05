<?php

arch('depends on nothing outside PHP, so it can become its own package')
    ->expect('App\Fsrs')
    ->toOnlyUse(['App\Fsrs']);
