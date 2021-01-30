@extends('backend.layouts.app')

@section('title', 'Cards | Edit')

@section('breadcrumb-links')
@endsection

@section('content')
{{ html()->modelForm($card, 'PATCH', route('admin.card.update', $card->id))->class('form-horizontal')->open() }}
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-sm-5">
                    <h4 class="card-title mb-0">
                        Card Edit
                    </h4>
                </div><!--col-->
            </div><!--row-->

            <hr>

            <div class="row mt-4 mb-4">
                <div class="col">
                    <div class="form-group row">
                    {{ html()->label('Player')->class('col-md-2 form-control-label')->for('player') }}

                        <div class="col-md-10">
                            {{ html()->text('player')
                                ->class('form-control')
                                ->placeholder('Player')
                                ->attribute('maxlength', 191)
                                ->required() }}
                        </div><!--col-->
                    </div><!--form-group-->

                    <div class="form-group row">
                    {{ html()->label('Year')->class('col-md-2 form-control-label')->for('year') }}

                        <div class="col-md-10">
                            {{ html()->text('year')
                                ->class('form-control')
                                ->placeholder('year')
                                ->attribute('maxlength', 191)
                                ->required() }}
                        </div><!--col-->
                    </div><!--form-group-->

                    <div class="form-group row">
                    {{ html()->label('Brand')->class('col-md-2 form-control-label')->for('brand') }}

                        <div class="col-md-10">
                            {{ html()->text('brand')
                                ->class('form-control')
                                ->placeholder('Brand')
                                ->attribute('maxlength', 191)
                                ->required() }}
                        </div><!--col-->
                    </div><!--form-group-->

                    <div class="form-group row">
                    {{ html()->label('Card')->class('col-md-2 form-control-label')->for('card') }}

                        <div class="col-md-10">
                            {{ html()->text('card')
                                ->class('form-control')
                                ->placeholder('Card')
                                ->attribute('maxlength', 191)
                                ->required() }}
                        </div><!--col-->
                    </div><!--form-group-->

                    <div class="form-group row">
                    {{ html()->label('Rc')->class('col-md-2 form-control-label')->for('rc') }}

                        <div class="col-md-10">
                            {{ html()->select('rc',[0=>'Yes', 1=>'No'])
                                ->class('form-control')
                                ->required() }}
                        </div><!--col-->
                    </div><!--form-group-->

                    <div class="form-group row">
                    {{ html()->label('Variation')->class('col-md-2 form-control-label')->for('variation') }}

                        <div class="col-md-10">
                            {{ html()->text('variation')
                                ->class('form-control')
                                ->placeholder('Variation')
                                ->attribute('maxlength', 191)
                                ->required() }}
                        </div><!--col-->
                    </div><!--form-group-->

                    <div class="form-group row">
                    {{ html()->label('Grade')->class('col-md-2 form-control-label')->for('grade') }}

                        <div class="col-md-10">
                            {{ html()->text('grade')
                                ->class('form-control')
                                ->placeholder('Grade')
                                ->attribute('maxlength', 191) }}
                        </div><!--col-->
                    </div><!--form-group-->

                    <div class="form-group row">
                    {{ html()->label('Qualifiers')->class('col-md-2 form-control-label')->for('qualifiers') }}

                        <div class="col-md-10">
                            {{ html()->textarea('qualifiers')
                                ->class('form-control')
                                ->placeholder('Qualifiers')
                                ->required() }}
                        </div><!--col-->
                    </div><!--form-group-->



                </div><!--col-->
            </div><!--row-->
        </div><!--card-body-->

        <div class="card-footer">
            <div class="row">
                <div class="col">
                    {{ form_cancel(route('admin.card.index'), __('buttons.general.cancel')) }}
                </div><!--col-->

                <div class="col text-right">
                    {{ form_submit(__('buttons.general.crud.update')) }}
                </div><!--row-->
            </div><!--row-->
        </div><!--card-footer-->
    </div><!--card-->
{{ html()->closeModelForm() }}
@endsection
