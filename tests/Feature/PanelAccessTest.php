<?php

it('deja entrar al panel a la oficina', function (string $role) {
    $this->actingAs(userWithRole($role))->get('/admin')->assertOk();
})->with(['admin', 'coordinacion', 'ventas', 'mantenimiento']);

it('no deja entrar al panel a capitanes ni choferes', function (string $role) {
    $this->actingAs(userWithRole($role))->get('/admin')->assertForbidden();
})->with(['capitan', 'chofer']);
