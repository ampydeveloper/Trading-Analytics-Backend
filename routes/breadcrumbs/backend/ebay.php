<?php

Breadcrumbs::for('admin.ebay.index', function ($trail) {
    $trail->push('Ebay Items List', route('admin.ebay.index'));
});
