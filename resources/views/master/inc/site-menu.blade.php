
@php
  
    $links = json_decode($menus->menus, true);
 
@endphp

<nav class="site-menu">
    <ul>
      
        @foreach ($links as $link)
            @php
             $href = Helper::getHref($link); 
            
            @endphp

            @if (!array_key_exists("children",$link))
                <li class="@if($href == URL::current() ) active  @endif">
                    <a href="{{ $link["href"] == null ? $href : $link["href"] }}" target="{{$link["target"]}}">{{$link["text"]}}</a>
                </li>
            @else
                <li class="t-h-dropdown">
                    <a class="main-link" href="{{$href}}" {{$link["target"]}}>{{$link["text"]}}<i class="icon-chevron-down"></i></a>

                    <div class="t-h-dropdown-menu">
                        @foreach ($link["children"] as $level2)

                        @php
                            $l2Href = Helper::getHref($level2);
                            
                        @endphp
                        
                        <a class="@if($l2Href == URL::current() ) active  @endif" href="{{$l2Href}}" target="{{$level2["target"]}}">
                            <i class="icon-chevron-right pr-2"></i>
                            {{$level2["text"]}}
                        </a>
                        @endforeach
                    </div>

                </li>
            @endif

        @endforeach
    </ul>
</nav>