@extends('frontend.layouts.app')

@section('title', 'Check our Stoxticker')

@section('meta')
<meta property="twitter:card" content="summary_large_image">
<meta property="twitter:url" content="<?php echo env('VUE_URL'); ?>stoxticker">
<meta property="twitter:title" content="Check our Stoxticker">
<meta property="twitter:image" content="<?php echo url('img/stoxticker-graph-share.png'); ?>">
<meta property="twitter:description" content="Stoxticker <?php echo $data['sale']; ?> Total Slabs <?php echo $data['total']; ?> Price Change <?php echo $data['change']; ?> Percentage Change <?php echo $data['change_pert']; ?>% Stoxticker URL <?php echo env('VUE_URL') . 'stoxticker'; ?>">
<meta name="twitter:site" content="@Slabstox">
<meta name="twitter:image:alt" content="Check our Stoxticker">

<meta name="title" content="Check our Stoxticker">
<meta name="description" content="Stoxticker <?php echo $data['sale']; ?> Total Slabs <?php echo $data['total']; ?> Price Change <?php echo $data['change']; ?> Percentage Change <?php echo $data['change_pert']; ?>% Stoxticker URL <?php echo env('VUE_URL') . 'stoxticker'; ?>">

<meta property="og:site_name" content="Slabstox">
<meta property="og:type" content="article">
<meta property="fb:app_id" content="2791823984386463">

<meta property="og:url" content="<?php echo env('VUE_URL'); ?>stoxticker">
<meta property="og:title" content="Check our Stoxticker">
<meta property="og:image" content="<?php echo url('img/stoxticker-graph-share.png'); ?>">
<meta property="og:description" content="Stoxticker <?php echo $data['sale']; ?> Total Slabs <?php echo $data['total']; ?> Price Change <?php echo $data['change']; ?> Percentage Change <?php echo $data['change_pert']; ?>% Stoxticker URL <?php echo env('VUE_URL') . 'stoxticker'; ?>">

<!--<meta property="article:published_time" content="<?php // echo \Carbon\Carbon::parse($data['last_updated'])->timestamp; ?>">-->
<meta property="article:author" content="Slabstox">
@endsection

@section('content')
<div class="row mb-4">
    Loading SlabStox
</div><!--row-->
@endsection
