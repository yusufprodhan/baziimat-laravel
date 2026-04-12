
<div class="s-r-inner">
    @foreach ($items as $item)
    <div class="product-card p-col">
        <a class="product-thumb" href="{{route('front.product',$item->slug)}}">
            <img class="lazy" alt="Product" src="{{url('/images/'.$item->thumbnail)}}" style=""></a>
        <div class="product-card-body">
            <h3 class="product-title"><a href="{{route('front.product',$item->slug)}}">
                {{ Str::limit($item->name, 35) }}
            </a></h3>
            <div class="rating-stars">
                {!! Helper::renderStarRating($item->reviews->avg('rating')) !!}
            </div>
            <h4 class="product-price">
                {{PriceHelper::grandCurrencyPrice($item)}}
            </h4>
        </div>
    </div>
    @endforeach
    
</div>
<div class="bottom-area">
    <a id="view_all_search_" href="javascript:;">{{ __('View all result') }}</a>
</div>