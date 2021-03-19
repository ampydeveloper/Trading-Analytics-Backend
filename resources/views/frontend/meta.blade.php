@extends('frontend.layouts.app')

@section('title', app_name() . ' | ' . __('navs.general.home'))

@section('meta')
<meta  data-hid="card-og-url" property="og:url" content="<?php echo env('APP_URL'); ?>/card-data/?id=<?php echo $card_details['id']; ?>">
<meta  data-hid="card-og-site_name" property="og:site_name" content="Slabstox">
<meta  data-hid="card-og-type" property="og:type" content="website">

<meta  data-hid="card-og-title" property="og:title" content="<?php echo $card_details['title']; ?>">
<meta  data-hid="card-og-image" property="og:image" content="<?php echo $card_details['cardImage']; ?>">
<meta  data-hid="card-og-description" property="og:description" content="<?php echo $card_details['title']; ?> SX Value $<?php echo $card_details['sx']; ?> Card Cost Change $<?php echo $card_details['dollar_diff']; ?> <?php echo $card_details['pert_diff']; ?>% Slab Image <?php echo $card_details['cardImage']; ?>">

<meta  data-hid="card-article-published_time" property="article:published_time" content="<?php echo Carbon::create($card_details['updated_at'])->format('F d Y \- h:i:s A'); ?>">
<meta  data-hid="card-article-author" property="article:author" content="Slabstox">

<meta  data-hid="card-twitter-url" property="twitter:url" content="<?php echo env('APP_URL'); ?>/card-data/?id=<?php echo $card_details['id']; ?>">
<meta  data-hid="card-twitter-title" property="twitter:title" content="<?php echo $card_details['title']; ?>">
<meta  data-hid="card-twitter-image" property="twitter:image" content="<?php echo $card_details['cardImage']; ?>">
<meta  data-hid="card-twitter-card" property="twitter:card" content="<?php echo $card_details['cardImage']; ?>">
<meta  data-hid="card-twitter-description" property="twitter:description" content="<?php echo $card_details['title']; ?> SX Value $<?php echo $card_details['sx']; ?> Card Cost Change $<?php echo $card_details['dollar_diff']; ?> <?php echo $card_details['pert_diff']; ?>% Slab Image <?php echo $card_details['cardImage']; ?>">

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
