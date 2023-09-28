<!!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
  <title>Email to User</title>
  </head>

<body>
  <div style="background:#f5f5f5;border:.0625rem solid #ccc;float:left;font-family:Calibri,arial,serif;width:43.75rem">
    <div style="background:#222;float:left;padding:.625rem;width:100%;">
      <img src="{!! env('SITE_BASE_URL') !!}/images/dagensmenu-logo.png" alt="dagensmenu logo">
      <h1 style="color:#fff;font-family:Calibri,arial,serif;font-weight:500;margin:.625rem 0;padding:0;text-align:center"><a href="{!! env('SITE_BASE_URL') !!}" target="_blank">{!! Translate::msg("Dagensmenu.dk") !!}</a></h1>
      <p style="margin: 10px 0; font-size: 18px; color: #6d6d6d; font-weight: 500; text-align: center;">{!! Translate::msg("Denmark largest restaurant & take away guide") !!}
    </div>
    <div style="float:left;font-size:.9375rem;padding:.625rem;width:42.5rem; margin-top:.625rem; margin-bottom:.625rem;">
      <p style="margin:0rem; padding:0rem;">
      {!! nl2br($message)  !!}
      </p>

      <h3 style="font-size:1.25rem; margin:.9375rem 0 0 0rem; padding:0rem; font-family:Calibri,arial,serif; text-align:center; color:#5ca942;"></h3>
      <div style="float:left; margin-top:.625rem; color:#666666;">
      {!! Translate::msg("Best regards" )!!},<br />
        <strong style="color:#5cb85c;"><em>{!! Translate::msg("Website Team") !!}</em></strong>
      </div>
    </div>
    <div style="background:#5cb85c;color:#fff;float:left;padding:.9375rem .625rem;width:100%">
      <div style="float:left;width:11.875rem"><a href="{!! env('SITE_BASE_URL') !!}" style="color:#fff;outline:0;text-decoration:none">{!! Translate::msg("Contact Us") !!}</a></div>
      <div style="float:left;text-align:center;width:18.75rem">{!! Translate::msg("Copyright") !!} &copy; {{ date('Y') }} <a href="{!! env('SITE_BASE_URL') !!}" style="color:#fff;outline:0;text-decoration:none">{!! env('DOMAIN_NAME') !!}</a><br>All rights reserved.</div>
      <div style="float:left;text-align:right;width:11.875rem">
        <ul style="list-style-type:none;margin:0;padding:0;">
          <li style="display:inline;"><a href="https://www.facebook.com/Dagens-Menu-dk-1517729645198430" style="color:#fff;outline:0;text-decoration:none"><img src="{!! env('SITE_BASE_URL') !!}/images/icon-facebook.png" alt="icon-facebook"></a></li>
          <li style="display:inline;margin-left:.3125rem"><a href="https://www.youtube.com/watch?v=Oy3V4ug7dug" style="color:#fff;outline:0;text-decoration:none"><img src="{!! env('SITE_BASE_URL') !!}/images/icon-youtube.png" alt="icon-youtube"></a></li>
        </ul>
      </div>
    </div>
  </div>
</body>
</html>