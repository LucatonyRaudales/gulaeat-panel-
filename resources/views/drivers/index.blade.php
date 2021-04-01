@extends('layouts.app')
<style>
  .sweet-alert.sweetalert-lg { width: 1100px; height: 800px; margin: auto; transform: translateX(-50%);}
</style>

@section('content')
<!-- Content Header (Page header) -->
<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0 text-dark">{{trans('lang.driver_plural')}}<small class="ml-3 mr-3">|</small><small>{{trans('lang.driver_desc')}}</small></h1>
      </div><!-- /.col -->
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="{{url('/dashboard')}}"><i class="fa fa-dashboard"></i> {{trans('lang.dashboard')}}</a></li>
          <li class="breadcrumb-item"><a href="{!! route('drivers.index') !!}">{{trans('lang.driver_plural')}}</a>
          </li>
          <li class="breadcrumb-item active">{{trans('lang.driver_table')}}</li>
        </ol>
      </div><!-- /.col -->
    </div><!-- /.row -->
  </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<div class="content">
  <div class="clearfix"></div>
  @include('flash::message')
  <div class="card">
    <div class="card-header">
      <ul class="nav nav-tabs align-items-end card-header-tabs w-100">
        <li class="nav-item">
          <a class="nav-link active" href="{!! url()->current() !!}"><i class="fa fa-list mr-2"></i>{{trans('lang.driver_table')}}</a>
        </li>
        @can('drivers.create')
        <li class="nav-item">
          <a class="nav-link" href="{!! route('drivers.create') !!}"><i class="fa fa-plus mr-2"></i>{{trans('lang.driver_create')}}</a>
        </li>
        @endcan
        @include('layouts.right_toolbar', compact('dataTable'))
      </ul>
    </div>
    <div class="card-body">
      @include('drivers.table')
      <div class="clearfix"></div>
    </div>
  </div>
</div>
@endsection

<script>
  window.addEventListener('load', function () {
    setInterval('window.location.reload()', 180000);
  })

  function locate(id){

    $.ajax({
      url: "https://gulaeats.com.mx/public/api/drivers/" + id,
      type: "get",
      data: id,
      headers: {
        'X-CSRF-Token': '{{ csrf_token() }}',
        'Authorization': 'Bearer qXaWaoe6S09TYg1VblsL2cgbmCdhSpH4pzxY5ZjRDTj68G8l5fJ29N4kBHnY'
      },
      success: function (response) {
        showLocation(response.data.lat, response.data.lng);
      }
    });
  }

  function showLocation(lat, lng) {

    if (lat === "" || lat === 0 || lat == null|| lng === "" || lng === 0 || lng == null){
      swal({
        title: "<b class='text-black-50'>Ubicacion del repartidor</b><hr>",
        html: true,
        confirmButtonText: "{{trans('lang.ok')}}",
        confirmButtonClass: "btn-success",
        text: 'El repartidor seleccionado no tiene asignada una ubicacion en este momento.'
      });

    }else {
      swal({
        title: "<b class='text-black-50'>Ubicacion del repartidor</b><hr>",
        html:true,
        customClass: "sweetalert-lg",
        confirmButtonText: "{{trans('lang.ok')}}",
        confirmButtonClass: "btn-success",
        text: '<iframe width="100%" height="600" src="https://maps.google.com/maps?q=' +lat + ',' + lng + '&hl=es&z=15&amp;output=embed"></iframe><br />'
      });
    }
  }
</script>

