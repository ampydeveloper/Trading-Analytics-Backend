<?php

Breadcrumbs::for('admin.card.index', function ($trail) {
    $trail->push(__('strings.backend.card.title'), route('admin.card.index'));
});

Breadcrumbs::for('admin.card.create', function ($trail) {
    $trail->push(__('strings.backend.card.title'), route('admin.card.index'));
    $trail->push('Create');
});

Breadcrumbs::for('admin.card.edit', function ($trail) {
    $trail->push(__('strings.backend.card.title'), route('admin.card.index'));
    $trail->push('Edit');
});

Breadcrumbs::for('admin.card.import', function ($trail) {
    $trail->push(__('strings.backend.card.import'), route('admin.card.import'));
});


