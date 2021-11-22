@extends('frontend.layouts.app')

@section('title', $card_details['title'])

@section('meta')
<meta property="twitter:card" content="summary_large_image">
<meta property="twitter:url" content="<?php echo env('VUE_URL'); ?>card-data/?id=<?php echo $card_details['id']; ?>">
<meta property="twitter:title" content="<?php echo $card_details['title']; ?>">
<meta property="twitter:image" content="<?php echo $card_details['cardImage']; ?>">
<meta property="twitter:description" content="<?php echo $card_details['title']; ?> SX Value $<?php echo $card_details['sx']; ?> Price Change $<?php echo $card_details['dollar_diff']; ?> Percentage Change <?php echo $card_details['pert_diff']; ?>% Slab URL <?php echo env('VUE_URL') . 'card-data/?id=' . $card_details['id']; ?>">
<meta name="twitter:site" content="@Slabstox">
<meta name="twitter:image:alt" content="<?php echo $card_details['title']; ?>">

<meta name="title" content="<?php echo $card_details['title']; ?>">
<meta name="description" content="<?php echo $card_details['title']; ?> SX Value $<?php echo $card_details['sx']; ?> Price Change $<?php echo $card_details['dollar_diff']; ?> Percentage Change <?php echo $card_details['pert_diff']; ?>% Slab URL <?php echo env('VUE_URL') . 'card-data/?id=' . $card_details['id']; ?>">

<meta property="og:site_name" content="Slabstox">
<meta property="og:type" content="article">
<meta property="fb:app_id" content="2791823984386463">

<meta property="og:title" content="<?php echo $card_details['title']; ?>">
<meta property="og:description" content="<?php echo $card_details['title']; ?> SX Value $<?php echo $card_details['sx']; ?> Price Change $<?php echo $card_details['dollar_diff']; ?> Percentage Change <?php echo $card_details['pert_diff']; ?>% Slab URL <?php echo env('VUE_URL') . 'card-data/?id=' . $card_details['id']; ?>">
<meta property="og:image" content="<?php echo $card_details['cardImage']; ?>">
<meta property="og:url" content="<?php echo env('VUE_URL'); ?>card-data/?id=<?php echo $card_details['id']; ?>">

<meta property="article:published_time" content="<?php echo \Carbon\Carbon::parse($card_details['updated_at'])->timestamp; ?>">
<meta property="article:author" content="Slabstox">
@endsection

@section('content')
<div class="row mb-4">
    Loading SlabStox
</div><!--row-->

@endsection