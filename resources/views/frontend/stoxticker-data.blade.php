@extends('frontend.layouts.app')

@section('title', app_name() . ' | ' . __('navs.general.home'))

@section('meta')
<meta name="title" content="Check our StoxTicker">
<meta name="description" content="StoxTicker@ <?php echo $data['sale']; ?> Total Slabs: <?php echo $data['total']; ?>">

<meta  data-hid="card-og-site_name" property="og:site_name" content="Slabstox">
<meta  data-hid="card-og-type" property="og:type" content="website">

<meta  data-hid="card-og-title" property="og:title" content="Check our StoxTicker">
<meta  data-hid="card-og-image" property="og:image" content="https://www.slabstox.com/wp-content/uploads/2020/06/logo-3.png">
<meta  data-hid="card-og-description" property="og:description" content="StoxTicker@ <?php echo $data['sale']; ?> Total Slabs: <?php echo $data['total']; ?>">

<meta  data-hid="card-article-published_time" property="article:published_time" content="<?php echo \Carbon\Carbon::createFromTimeStamp($data['last_updated'])->format('F d Y \- h:i:s A'); ?>">
<meta  data-hid="card-article-author" property="article:author" content="Slabstox">

<meta property="twitter:card" content="summary_large_image">
<meta  data-hid="card-twitter-url" property="twitter:url" content="<?php echo env('VUE_URL'); ?>stoxticker">
<meta  data-hid="card-twitter-title" property="twitter:title" content="Check our StoxTicker">
<meta  data-hid="card-twitter-image" property="twitter:image" content="https://www.slabstox.com/wp-content/uploads/2020/06/logo-3.png">
<meta  data-hid="card-twitter-description" property="twitter:description" content="StoxTicker@ <?php echo $data['sale']; ?> Total Slabs: <?php echo $data['total']; ?>">

@endsection

@section('content')
<?php
//dd($card_details);
?>
<div class="row mb-4">
    <div class="col">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-home"></i> @lang('navs.general.home')
            </div>
            <div class="card-body">
                @lang('strings.frontend.welcome_to', ['place' => app_name()])
            </div>
        </div><!--card-->
    </div><!--col-->
</div><!--row-->

@endsection
