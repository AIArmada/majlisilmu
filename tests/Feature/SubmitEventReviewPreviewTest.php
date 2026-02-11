<?php

it('shows a submission preview section on submit event page', function () {
    $this->get('/submit-event')
        ->assertSuccessful()
        ->assertSee(__('Pratonton Penghantaran'))
        ->assertSee(__('Semak ringkasan ini sebelum anda menghantar.'));
});
