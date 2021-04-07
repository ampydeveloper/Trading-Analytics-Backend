@extends('frontend.layouts.app')

@section('title', app_name() . ' | ' . __('navs.general.home'))

@section('meta')
<meta name="title" content="Check SX Stoxticker">
<meta name="description" content="
<?php if(isset($items['basketball'][0])){ ?>
Basketball: <?php echo $items['basketball'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['basketball'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['soccer'][0])){ ?>
Soccer: <?php echo $items['soccer'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['soccer'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['football'][0])){ ?>
Football: <?php echo $items['football'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['football'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['baseball'][0])){ ?>
Baseball: <?php echo $items['baseball'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['baseball'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['pokemon'][0])){ ?>
Pokemon: <?php echo $items['pokemon'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['pokemon'][0]->card->id; ?>
<?php } ?>">

<meta  data-hid="card-og-site_name" property="og:site_name" content="Slabstox">
<meta  data-hid="card-og-type" property="og:type" content="article">
<meta  data-hid="card-og-type" property="fb:app_id" content="2791823984386463">

<meta  data-hid="card-og-title" property="og:title" content="Check SX Stoxticker">
<meta  data-hid="card-og-image" property="og:image" content="https://www.slabstox.com/wp-content/uploads/2020/06/logo-3.png">
<meta  data-hid="card-og-description" property="og:description" content="
<?php if(isset($items['basketball'][0])){ ?>
Basketball: <?php echo $items['basketball'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['basketball'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['soccer'][0])){ ?>
Soccer: <?php echo $items['soccer'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['soccer'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['football'][0])){ ?>
Football: <?php echo $items['football'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['football'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['baseball'][0])){ ?>
Baseball: <?php echo $items['baseball'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['baseball'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['pokemon'][0])){ ?>
Pokemon: <?php echo $items['pokemon'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['pokemon'][0]->card->id; ?>
<?php } ?>">

<!--<meta  data-hid="card-article-published_time" property="article:published_time" content="<?php // echo \Carbon\Carbon::createFromTimeStamp($data['last_updated'])->format('F d Y \- h:i:s A'); ?>">-->
<meta  data-hid="card-article-author" property="article:author" content="Slabstox">

<meta property="twitter:card" content="summary_large_image">
<meta  data-hid="card-twitter-url" property="twitter:url" content="<?php echo env('VUE_URL'); ?>stoxticker">
<meta  data-hid="card-twitter-title" property="twitter:title" content="Check SX Stoxticker">
<meta  data-hid="card-twitter-image" property="twitter:image" content="https://www.slabstox.com/wp-content/uploads/2020/06/logo-3.png">
<meta  data-hid="card-twitter-description" property="twitter:description" content="
<?php if(isset($items['basketball'][0])){ ?>
Basketball: <?php echo $items['basketball'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['basketball'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['soccer'][0])){ ?>
Soccer: <?php echo $items['soccer'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['soccer'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['football'][0])){ ?>
Football: <?php echo $items['football'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['football'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['baseball'][0])){ ?>
Baseball: <?php echo $items['baseball'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['baseball'][0]->card->id; ?> 
<?php } ?>
<?php if(isset($items['pokemon'][0])){ ?>
Pokemon: <?php echo $items['pokemon'][0]->card->title; ?> <?php echo env('VUE_URL').'card-data/?id='. $items['pokemon'][0]->card->id; ?>
<?php } ?>">

@endsection

@section('content')
<?php
//dd($finalData);
?>
<div class="row mb-4">
    Loading SlabStox
</div><!--row-->

@endsection
