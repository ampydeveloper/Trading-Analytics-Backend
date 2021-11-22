@extends('frontend.layouts.app')

@section('title', 'Check StoxTicker: ' .$finalData['board']->name )

@section('meta')
<meta property="twitter:card" content="summary_large_image">
<meta property="twitter:url" content="<?php echo env('VUE_URL'); ?>stox-details?board=<?php echo $finalData['board']->id; ?>">
<meta property="twitter:title" content="Check StoxTicker: <?php echo $finalData['board']->name; ?>">
<meta property="twitter:image" content="<?php echo url('img/stoxticker-graph-share.png'); ?>">
<meta property="twitter:description" content="StoxTicker: <?php echo $finalData['board']->name; ?> Stoxticker Value $<?php echo $finalData['total_card_value']; ?> SX Change $<?php echo $finalData['sx_value']; ?> Percentage Change <?php echo $finalData['pert_diff']; ?>%">
<meta name="twitter:site" content="@Slabstox">
<meta name="twitter:image:alt" content="Check StoxTicker: <?php echo $finalData['board']->name; ?>">

<meta name="title" content="Check StoxTicker: <?php echo $finalData['board']->name; ?>">
<meta name="description" content="StoxTicker: <?php echo $finalData['board']->name; ?> Stoxticker Value $<?php echo $finalData['total_card_value']; ?> SX Change $<?php echo $finalData['sx_value']; ?> Percentage Change <?php echo $finalData['pert_diff']; ?>%">

<meta property="og:site_name" content="Slabstox">
<meta property="og:type" content="article">
<meta property="fb:app_id" content="2791823984386463">

<meta property="og:url" content="<?php echo env('VUE_URL'); ?>stox-details?board=<?php echo $finalData['board']->id; ?>">
<meta property="og:title" content="Check StoxTicker: <?php echo $finalData['board']->name; ?>">
<meta property="og:image" content="<?php echo url('img/stoxticker-graph-share.png'); ?>">
<meta property="og:description" content="StoxTicker: <?php echo $finalData['board']->name; ?> Stoxticker Value $<?php echo $finalData['total_card_value']; ?> SX Change $<?php echo $finalData['sx_value']; ?> Percentage Change <?php echo $finalData['pert_diff']; ?>%">

<!--<meta property="article:published_time" content="<?php // echo \Carbon\Carbon::parse($data['last_updated'])->timestamp; ?>">-->
<meta  property="article:author" content="Slabstox">
@endsection

@section('content')
<div class="row mb-4">
    Loading SlabStox
</div><!--row-->
@endsection
