<?php

test('returns a successful response', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Videre Est Scire')
        ->assertSee('Fresh from the palengke')
        ->assertSee('Create a customer account');
});
