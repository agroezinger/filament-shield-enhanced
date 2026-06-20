<?php

use Agroezinger\FilamentShieldEnhanced\Support\ResourcePermissionKeyBuilder;

describe('ResourcePermissionKeyBuilder', function () {

    it('produces Action:Model format with pascal case and colon separator', function () {
        $key = ResourcePermissionKeyBuilder::build(
            entity: 'App\\Filament\\Resources\\MemberResource',
            affix: 'ViewContactInfo',
            subject: 'Member',
            case: 'pascal',
            separator: ':',
        );

        expect($key)->toBe('ViewContactInfo:Member');
    });

    it('applies pascal case to both parts', function () {
        $key = ResourcePermissionKeyBuilder::build(
            entity: 'SomeResource',
            affix: 'exportFinance',
            subject: 'memberProfile',
            case: 'pascal',
            separator: ':',
        );

        expect($key)->toBe('ExportFinance:MemberProfile');
    });

    it('applies snake case to both parts', function () {
        $key = ResourcePermissionKeyBuilder::build(
            entity: 'SomeResource',
            affix: 'ViewContactInfo',
            subject: 'Member',
            case: 'snake',
            separator: ':',
        );

        expect($key)->toBe('view_contact_info:member');
    });

    it('applies upper_snake case to both parts', function () {
        $key = ResourcePermissionKeyBuilder::build(
            entity: 'SomeResource',
            affix: 'viewContactInfo',
            subject: 'member',
            case: 'upper_snake',
            separator: ':',
        );

        expect($key)->toBe('VIEW_CONTACT_INFO:MEMBER');
    });

    it('applies camel case to both parts', function () {
        $key = ResourcePermissionKeyBuilder::build(
            entity: 'SomeResource',
            affix: 'ViewContactInfo',
            subject: 'Member',
            case: 'camel',
            separator: ':',
        );

        expect($key)->toBe('viewContactInfo:member');
    });

    it('applies kebab case to both parts', function () {
        $key = ResourcePermissionKeyBuilder::build(
            entity: 'SomeResource',
            affix: 'ViewContactInfo',
            subject: 'Member',
            case: 'kebab',
            separator: ':',
        );

        expect($key)->toBe('view-contact-info:member');
    });

    it('uses the configured separator', function () {
        $key = ResourcePermissionKeyBuilder::build(
            entity: 'SomeResource',
            affix: 'Export',
            subject: 'Member',
            case: 'pascal',
            separator: '_',
        );

        expect($key)->toBe('Export_Member');
    });

    it('produces a two-part key — never three-part like Page keys', function () {
        $key = ResourcePermissionKeyBuilder::build(
            entity: 'SomeResource',
            affix: 'Export',
            subject: 'Member',
            case: 'pascal',
            separator: ':',
        );

        expect(substr_count($key, ':'))->toBe(1);
    });

    it('matches Shield CRUD permission format', function () {
        // Shield generates e.g. "ViewAny:Member" for viewAny policy method.
        // Our custom actions should look exactly the same.
        $crud = ResourcePermissionKeyBuilder::build(
            entity: 'SomeResource',
            affix: 'viewAny',
            subject: 'Member',
            case: 'pascal',
            separator: ':',
        );
        $custom = ResourcePermissionKeyBuilder::build(
            entity: 'SomeResource',
            affix: 'Export',
            subject: 'Member',
            case: 'pascal',
            separator: ':',
        );

        expect($crud)->toBe('ViewAny:Member');
        expect($custom)->toBe('Export:Member');
        // Both have exactly the same format: Action:Model
        expect(substr_count($crud, ':'))->toBe(substr_count($custom, ':'));
    });

});
