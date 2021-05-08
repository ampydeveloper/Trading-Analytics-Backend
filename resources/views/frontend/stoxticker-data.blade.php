@extends('frontend.layouts.app')

@section('title', 'Check our Stoxticker')

@section('meta')
<meta name="title" content="Check our Stoxticker">
<meta name="description" content="Stoxticker <?php echo $data['sale']; ?> Total Slabs <?php echo $data['total']; ?> Price Change <?php echo $data['change']; ?> Percentage Change <?php echo $data['change_pert']; ?>% Stoxticker URL <?php echo env('VUE_URL').'stoxticker'; ?>">

<meta  data-hid="card-og-site_name" property="og:site_name" content="Slabstox">
<meta  data-hid="card-og-type" property="og:type" content="article">
<meta  data-hid="card-og-type" property="fb:app_id" content="2791823984386463">

<meta  data-hid="card-og-url" property="og:url" content="<?php echo env('VUE_URL'); ?>stoxticker">
<meta  data-hid="card-og-title" property="og:title" content="Check our Stoxticker">
<meta  data-hid="card-og-image" property="og:image" content="<?php echo url('img/stoxticker-graph-share.png'); ?>">
<meta  data-hid="card-og-description" property="og:description" content="Stoxticker <?php echo $data['sale']; ?> Total Slabs <?php echo $data['total']; ?> Price Change <?php echo $data['change']; ?> Percentage Change <?php echo $data['change_pert']; ?>% Stoxticker URL <?php echo env('VUE_URL').'stoxticker'; ?>">

<meta  data-hid="card-article-published_time" property="article:published_time" content="<?php echo \Carbon\Carbon::createFromTimeStamp($data['last_updated'])->format('F d Y \- h:i:s A'); ?>">
<meta  data-hid="card-article-author" property="article:author" content="Slabstox">

<meta property="twitter:card" content="summary_large_image">
<meta  data-hid="card-twitter-url" property="twitter:url" content="<?php echo env('VUE_URL'); ?>stoxticker">
<meta  data-hid="card-twitter-title" property="twitter:title" content="Check our Stoxticker@">
<meta  data-hid="card-twitter-image" property="twitter:image" content="<?php echo url('img/stoxticker-graph-share.png'); ?>">
<meta  data-hid="card-twitter-description" property="twitter:description" content="Stoxticker <?php echo $data['sale']; ?> Total Slabs <?php echo $data['total']; ?> Price Change <?php echo $data['change']; ?> Percentage Change <?php echo $data['change_pert']; ?>% Stoxticker URL <?php echo env('VUE_URL').'stoxticker'; ?>">

@endsection

@section('content')
<div class="row mb-4">
    Loading SlabStox
</div><!--row-->

@endsection
