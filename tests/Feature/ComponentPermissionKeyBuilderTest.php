<?php

use Agroezinger\FilamentShieldEnhanced\Support\ComponentPermissionKeyBuilder;

// Lives in Feature (not Unit) because build() reads the permission_prefix from
// config() internally — same as PagePermissionKeyBuilder — and Unit tests in
// this package run without a Laravel app bootstrap (see tests/Pest.php).

describe('ComponentPermissionKeyBuilder', function () {

    it('produces Component:Action:Subject format with pascal case and colon separator', function () {
        $key = ComponentPermissionKeyBuilder::build(
            entity: 'App\\Livewire\\CommentComponent',
            affix: 'delete',
            subject: 'CommentComponent',
            case: 'pascal',
            separator: ':',
        );

        expect($key)->toBe('Component:Delete:CommentComponent');
    });

    it('uses the configured permission_prefix', function () {
        config(['filament-shield-enhanced.components.permission_prefix' => 'Widget']);

        $key = ComponentPermissionKeyBuilder::build(
            entity: 'App\\Livewire\\CommentComponent',
            affix: 'delete',
            subject: 'CommentComponent',
            case: 'pascal',
            separator: ':',
        );

        config(['filament-shield-enhanced.components.permission_prefix' => 'Component']); // reset

        expect($key)->toBe('Widget:Delete:CommentComponent');
    });

    it('applies snake case to all three parts', function () {
        $key = ComponentPermissionKeyBuilder::build(
            entity: 'SomeComponent',
            affix: 'Delete',
            subject: 'CommentComponent',
            case: 'snake',
            separator: ':',
        );

        expect($key)->toBe('component:delete:comment_component');
    });

    it('applies upper_snake case to all three parts', function () {
        $key = ComponentPermissionKeyBuilder::build(
            entity: 'SomeComponent',
            affix: 'delete',
            subject: 'commentComponent',
            case: 'upper_snake',
            separator: ':',
        );

        expect($key)->toBe('COMPONENT:DELETE:COMMENT_COMPONENT');
    });

    it('applies camel case to all three parts', function () {
        $key = ComponentPermissionKeyBuilder::build(
            entity: 'SomeComponent',
            affix: 'Delete',
            subject: 'CommentComponent',
            case: 'camel',
            separator: ':',
        );

        expect($key)->toBe('component:delete:commentComponent');
    });

    it('applies kebab case to all three parts', function () {
        $key = ComponentPermissionKeyBuilder::build(
            entity: 'SomeComponent',
            affix: 'Delete',
            subject: 'CommentComponent',
            case: 'kebab',
            separator: ':',
        );

        expect($key)->toBe('component:delete:comment-component');
    });

    it('uses the configured separator', function () {
        $key = ComponentPermissionKeyBuilder::build(
            entity: 'SomeComponent',
            affix: 'Delete',
            subject: 'CommentComponent',
            case: 'pascal',
            separator: '_',
        );

        expect($key)->toBe('Component_Delete_CommentComponent');
    });

    it('produces a three-part key — same shape as Page keys, unlike two-part Resource keys', function () {
        $key = ComponentPermissionKeyBuilder::build(
            entity: 'SomeComponent',
            affix: 'Delete',
            subject: 'CommentComponent',
            case: 'pascal',
            separator: ':',
        );

        expect(substr_count($key, ':'))->toBe(2);
    });

});
