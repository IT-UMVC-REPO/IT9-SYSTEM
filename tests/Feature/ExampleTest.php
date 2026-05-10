<?php

test('returns a successful response', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Your Local Market, Delivered')
        ->assertSee('Fresh from the palengke')
        ->assertSee('Create a customer account');
});
