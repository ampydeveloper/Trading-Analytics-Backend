@extends('frontend.layouts.app')

@section('title', app_name() . ' | ' . __('navs.general.home'))

@section('meta')
<meta name="title" content="Check StoxTicker: <?php echo $finalData['board']->name; ?>">
<meta name="description" content="StoxTicker: <?php echo $finalData['board']->name; ?> Stoxticker Value $<?php echo $finalData['total_card_value']; ?> SX Change $<?php echo $finalData['sx_value']; ?> Percentage Change <?php echo $finalData['pert_diff']; ?>%">

<meta  data-hid="card-og-site_name" property="og:site_name" content="Slabstox">
<meta  data-hid="card-og-type" property="og:type" content="article">
<meta  data-hid="card-og-type" property="fb:app_id" content="2791823984386463">

<meta  data-hid="card-og-url" property="og:url" content="<?php echo env('VUE_URL'); ?>stox-details?board=<?php echo $finalData['board']->id; ?>">
<meta  data-hid="card-og-title" property="og:title" content="Check StoxTicker: <?php echo $finalData['board']->name; ?>">
<meta  data-hid="card-og-image" property="og:image" content="https://www.slabstox.com/wp-content/uploads/2020/06/logo-3.png">
<meta  data-hid="card-og-description" property="og:description" content="StoxTicker: <?php echo $finalData['board']->name; ?> Stoxticker Value $<?php echo $finalData['total_card_value']; ?> SX Change $<?php echo $finalData['sx_value']; ?> Percentage Change <?php echo $finalData['pert_diff']; ?>%">

<!--<meta  data-hid="card-article-published_time" property="article:published_time" content="<?php // echo \Carbon\Carbon::createFromTimeStamp($data['last_updated'])->format('F d Y \- h:i:s A'); ?>">-->
<meta  data-hid="card-article-author" property="article:author" content="Slabstox">

<meta property="twitter:card" content="summary_large_image">
<meta  data-hid="card-twitter-url" property="twitter:url" content="<?php echo env('VUE_URL'); ?>stox-details?board=<?php echo $finalData['board']->id; ?>">
<meta  data-hid="card-twitter-title" property="twitter:title" content="Check StoxTicker: <?php echo $finalData['board']->name; ?>">
<meta  data-hid="card-twitter-image" property="twitter:image" content="https://www.slabstox.com/wp-content/uploads/2020/06/logo-3.png">
<meta  data-hid="card-twitter-description" property="twitter:description" content="StoxTicker: <?php echo $finalData['board']->name; ?> Stoxticker Value $<?php echo $finalData['total_card_value']; ?> SX Change $<?php echo $finalData['sx_value']; ?> Percentage Change <?php echo $finalData['pert_diff']; ?>%">

@endsection

@section('content')
<?php
//dd($finalData);
?>
<div class="row mb-4">
    Loading SlabStox
</div><!--row-->
@endsection
