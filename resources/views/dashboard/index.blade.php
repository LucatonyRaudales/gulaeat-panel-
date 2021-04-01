@extends('layouts.app')

<style>
    #mapCanvas {
        width: 100%;
        height: 100%;
        position:absolute
    }

    .slider {
        transition:all 2s ease-in-out;
        height:0px;
    }
</style>

@section('content')
    <!-- Content Header (Page header) -->
    <section class="content-header content-header{{setting('fixed_header')}}">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>{{trans('lang.dashboard')}}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="#">{{trans('lang.dashboard')}}</a></li>
                        <li class="breadcrumb-item active">{{trans('lang.dashboard')}}</li>
                    </ol>
                </div>
            </div>
        </div><!-- /.container-fluid -->
    </section>

    <div class="content mb-xl-5">
        <!-- Small boxes (Stat box) -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <!-- small box -->
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{$ordersCount}}</h3>

                        <p>{{trans('lang.dashboard_total_orders')}}</p>
                    </div>
                    <div class="icon">
                        <i class="fa fa-shopping-bag"></i>
                    </div>
                    <a href="{!! route('orders.index') !!}" class="small-box-footer">{{trans('lang.dashboard_more_info')}}
                        <i class="fa fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <!-- ./col -->
            <div class="col-lg-3 col-6">
                <!-- small box -->
                <div class="small-box bg-danger">
                    <div class="inner">
                        @if(setting('currency_right',false) != false)
                            <h3>{{$earning}}{{setting('default_currency')}}</h3>
                        @else
                            <h3>{{setting('default_currency')}}{{$earning}}</h3>
                        @endif

                        <p>{{trans('lang.dashboard_total_earnings')}} <span style="font-size: 11px">({{trans('lang.dashboard_after taxes')}})</span></p>
                    </div>
                    <div class="icon">
                        <i class="fa fa-money"></i>
                    </div>
                    <a href="{!! route('payments.index') !!}" class="small-box-footer">{{trans('lang.dashboard_more_info')}}
                        <i class="fa fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <!-- ./col -->
            <div class="col-lg-3 col-6">
                <!-- small box -->
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>{{$restaurantsCount}}</h3>
                        <p>{{trans('lang.restaurant_plural')}}</p>
                    </div>
                    <div class="icon">
                        <i class="fa fa-cutlery"></i>
                    </div>
                    <a href="{!! route('restaurants.index') !!}" class="small-box-footer">{{trans('lang.dashboard_more_info')}} <i class="fa fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <!-- ./col -->
            <div class="col-lg-3 col-6">
                <!-- small box -->
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>{{$membersCount}}</h3>

                        <p>{{trans('lang.dashboard_total_clients')}}</p>
                    </div>
                    <div class="icon">
                        <i class="fa fa-group"></i>
                    </div>
                    <a href="{!! route('users.index') !!}" class="small-box-footer">{{trans('lang.dashboard_more_info')}} <i class="fa fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <!-- ./col -->

        </div>
        <!-- /.row -->

        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header no-border">
                        <div class="d-flex justify-content-between">
                            <h3 class="card-title">{{trans('lang.earning_plural')}}</h3>
                            <a href="{!! route('payments.index') !!}">{{trans('lang.dashboard_view_all_payments')}}</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex">
                            <p class="d-flex flex-column">
                                @if(setting('currency_right',false) != false)
                                    <span class="text-bold text-lg">{{$earning}}{{setting('default_currency')}}</span>
                                @else
                                    <span class="text-bold text-lg">{{setting('default_currency')}}{{$earning}}</span>
                                @endif
                                <span>{{trans('lang.dashboard_earning_over_time')}}</span>
                            </p>
                            <p class="ml-auto d-flex flex-column text-right">
                                <span class="text-success"> {{$ordersCount}}</span>
                                <span class="text-muted">{{trans('lang.dashboard_total_orders')}}</span>
                            </p>
                        </div>
                        <!-- /.d-flex -->

                        <div class="position-relative mb-4">
                            <canvas id="sales-chart" height="200"></canvas>
                        </div>

                        <div class="d-flex flex-row justify-content-end">
                            <span class="mr-2"> <i class="fa fa-square text-primary"></i> {{trans('lang.dashboard_this_year')}} </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header no-border">
                        <h3 class="card-title">{{trans('lang.restaurant_plural')}}</h3>
                        <div class="card-tools">
                            <a href="{{route('restaurants.index')}}" class="btn btn-tool btn-sm"><i class="fa fa-bars"></i> </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped table-valign-middle">
                            <thead>
                            <tr>
                                <th>{{trans('lang.restaurant_image')}}</th>
                                <th>{{trans('lang.restaurant')}}</th>
                                <th>{{trans('lang.restaurant_address')}}</th>
                                <th>{{trans('lang.actions')}}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($restaurants as $restaurant)

                                <tr>
                                    <td>
                                        {!! getMediaColumn($restaurant, 'image','img-circle img-size-32 mr-2') !!}
                                    </td>
                                    <td>{!! $restaurant->name !!}</td>
                                    <td>
                                        {!! $restaurant->address !!}
                                    </td>
                                    <td class="text-center">
                                        <a href="{!! route('restaurants.edit',$restaurant->id) !!}" class="text-muted"> <i class="fa fa-edit"></i> </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg">
                <div class="card">
                    <div class="card-header" id="map-header">
                        <a class="btn btn-link slider" onclick="initMap()" id="map-title" style="height: 100%; width: 100%;">Ver Ubicacion de repartidores</a>
                    </div>
                    <div class="card-body p-0" id="card-map" hidden>
                        <div id="map" style="width: 1500px; height: 700px; margin: auto;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
@push('scripts_lib')
    <script src="{{asset('plugins/chart.js/Chart.min.js')}}"></script>
@endpush
@push('scripts')
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCm0CRu9TdSzxZxF7JOrxJfRm8fJXtgb3k"></script>

    <script type="text/javascript">
        var data = [1000, 2000, 3000, 2500, 2700, 2500, 3000];
        var labels = ['JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

        window.addEventListener('load', function () {
            setInterval(function (){
                $.ajax({
                    url: "https://gulaeats.com.mx/public/api/food/verifyPendings",
                    type: "get",
                    headers: {
                        'X-CSRF-Token': '{{ csrf_token() }}',
                        'Authorization': 'Bearer qXaWaoe6S09TYg1VblsL2cgbmCdhSpH4pzxY5ZjRDTj68G8l5fJ29N4kBHnY'
                    },
                    success: function (response) {
                        if (response.data > 0){
                            pendingFood();
                        }
                    }
                });

                /** Refresh drivers map **/
                let cardMap = jQuery("#card-map");
                if (cardMap.is(":visible")){
                    cardMap.remove();
                    jQuery("#map-header").after('<div class="card-body p-0" id="card-map" hidden><div id="map" style="width: 1500px; height: 700px; margin: auto;"></div></div>');
                    initMap();
                }
            }, 180000);
        })

        function renderChart(chartNode, data, labels) {
            var ticksStyle = {
                fontColor: '#495057',
                fontStyle: 'bold'
            };

            var mode = 'index';
            var intersect = true;
            return new Chart(chartNode, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            backgroundColor: '#007bff',
                            borderColor: '#007bff',
                            data: data
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    tooltips: {
                        mode: mode,
                        intersect: intersect
                    },
                    hover: {
                        mode: mode,
                        intersect: intersect
                    },
                    legend: {
                        display: false
                    },
                    scales: {
                        yAxes: [{
                            // display: false,
                            gridLines: {
                                display: true,
                                lineWidth: '4px',
                                color: 'rgba(0, 0, 0, .2)',
                                zeroLineColor: 'transparent'
                            },
                            ticks: $.extend({
                                beginAtZero: true,

                                // Include a dollar sign in the ticks
                                callback: function (value, index, values) {
                                    @if(setting('currency_right', '0') == '0')
                                        return "{{setting('default_currency')}} "+value;
                                    @else
                                        return value+" {{setting('default_currency')}}";
                                        @endif

                                }
                            }, ticksStyle)
                        }],
                        xAxes: [{
                            display: true,
                            gridLines: {
                                display: false
                            },
                            ticks: ticksStyle
                        }]
                    }
                }
            })
        }

        $(function () {
            'use strict'

            var $salesChart = $('#sales-chart')
            $.ajax({
                url: "{!! $ajaxEarningUrl !!}",
                success: function (result) {
                    $("#loadingMessage").html("");
                    var data = result.data[0];
                    var labels = result.data[1];
                    renderChart($salesChart, data, labels)
                },
                error: function (err) {
                    $("#loadingMessage").html("Error");
                }
            });
            //var salesChart = renderChart($salesChart, data, labels);
        })

        function initMap(){
            var cardMap = $("#card-map");
            var title = $("#map-title");

            if (cardMap.attr('hidden') === 'hidden'){
                cardMap.removeAttr('hidden');
                title.text("Cerrar panel de Repartidores");
            } else {
                cardMap.attr('hidden', 'hidden');
                title.text("Ver Ubicacion de repartidores");
            }

            window.scrollTo(0,document.body.scrollHeight);

            var locations = [];
            var drivers = [];

            $.ajax({
                url: "https://gulaeats.com.mx/public/api/dashboard/drivers",
                type: "get",
                headers: {
                    'X-CSRF-Token': '{{ csrf_token() }}',
                    'Authorization': 'Bearer DqDkgdRmcjpPPn1BhXiwFOJti2wBWhJ63Lt4QlSXwNJUibqWUpbge9gfszpR'
                },
                success: function (response) {
                    drivers = response.data;

                    for(var i=0;i<drivers.length;i++){
                        locations.push([drivers[i]['name'], parseFloat(drivers[i]['lat']), parseFloat(drivers[i]['lng']), i+1]);
                    }

                    var map = new google.maps.Map(document.getElementById('map'), {
                        zoom: 10,
                        center: new google.maps.LatLng(19.470883436999845, -99.13789551238334),
                        mapTypeId: google.maps.MapTypeId.ROADMAP
                    });

                    var infowindow = new google.maps.InfoWindow();

                    var marker, i;

                    for (i = 0; i < locations.length; i++) {
                        marker = new google.maps.Marker({
                            position: new google.maps.LatLng(locations[i][1], locations[i][2]),
                            map: map
                        });

                        google.maps.event.addListener(marker, 'click', (function(marker, i) {
                            return function() {
                                infowindow.setContent(locations[i][0]);
                                infowindow.open(map, marker);
                            }
                        })(marker, i));
                    }
                }
            });
        }

        function pendingFood(){
            const audio = new Audio("https://www.gulaeats.com.mx/public/sounds/ios_notification.mp3");
            audio.play();

            swal({
                title: "<b class='text-black-50'>Hay productos pendientes por aprobar</b><hr>",
                html: true,
                confirmButtonText: "{{trans('lang.ok')}}",
                confirmButtonClass: "btn-success",
                text: 'Por favor revise el panel de alimentos para gestionar los nuevos productos.'
            });
        }
    </script>
@endpush