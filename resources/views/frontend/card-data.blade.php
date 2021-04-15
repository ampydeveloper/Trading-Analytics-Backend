@extends('frontend.layouts.app')

@section('title', $card_details['title'])

@section('meta')
<meta name="title" content="<?php echo $card_details['title']; ?>">
<meta name="description" content="<?php echo $card_details['title']; ?> SX Value $<?php echo $card_details['sx']; ?> Price Change $<?php echo $card_details['dollar_diff']; ?> Percentage Change <?php echo $card_details['pert_diff']; ?>% Slab URL <?php echo env('VUE_URL').'card-data/?id='.$card_details['id']; ?>">

<meta  data-hid="card-og-site_name" property="og:site_name" content="Slabstox">
<meta  data-hid="card-og-type" property="og:type" content="article">
<meta  data-hid="card-og-type" property="fb:app_id" content="2791823984386463">

<meta  data-hid="card-og-url" property="og:url" content="<?php echo env('VUE_URL'); ?>card-data/?id=<?php echo $card_details['id']; ?>">
<meta  data-hid="card-og-title" property="og:title" content="<?php echo $card_details['title']; ?>">
<meta  data-hid="card-og-image" property="og:image" content="<?php echo $card_details['cardImage']; ?>">
<meta  data-hid="card-og-description" property="og:description" content="<?php echo $card_details['title']; ?> SX Value $<?php echo $card_details['sx']; ?> Price Change $<?php echo $card_details['dollar_diff']; ?> Percentage Change <?php echo $card_details['pert_diff']; ?>% Slab URL <?php echo env('VUE_URL').'card-data/?id='.$card_details['id']; ?>">

<meta  data-hid="card-article-published_time" property="article:published_time" content="<?php echo \Carbon\Carbon::createFromTimeStamp($card_details['updated_at'])->format('F d Y \- h:i:s A'); ?>">
<meta  data-hid="card-article-author" property="article:author" content="Slabstox">

<meta property="twitter:card" content="summary_large_image">
<meta  data-hid="card-twitter-url" property="twitter:url" content="<?php echo env('VUE_URL'); ?>card-data/?id=<?php echo $card_details['id']; ?>">
<meta  data-hid="card-twitter-title" property="twitter:title" content="<?php echo $card_details['title']; ?>">
<meta  data-hid="card-twitter-image" property="twitter:image" content="<?php echo $card_details['cardImage']; ?>">
<meta  data-hid="card-twitter-description" property="twitter:description" content="<?php echo $card_details['title']; ?> SX Value $<?php echo $card_details['sx']; ?> Price Change $<?php echo $card_details['dollar_diff']; ?> Percentage Change <?php echo $card_details['pert_diff']; ?>% Slab URL <?php echo env('VUE_URL').'card-data/?id='.$card_details['id']; ?>">

@endsection

@section('content')
<?php
//dd($card_details);
?>
<div class="row mb-4">
    Loading SlabStox
</div><!--row-->

@endsection
