<?php
const JWT_SECRET="CHANGE_ME";

function b64($d){return rtrim(strtr(base64_encode($d),'+/','-_'),'=');}

function jwt_encode($p){
    $h=b64(json_encode(["alg"=>"HS256","typ"=>"JWT"]));
    $b=b64(json_encode($p));
    $s=b64(hash_hmac("sha256","$h.$b",JWT_SECRET,true));
    return "$h.$b.$s";
}

function jwt_decode($t){
    [$h,$b,$s]=explode('.',$t);
    if($s!==b64(hash_hmac("sha256","$h.$b",JWT_SECRET,true))) return false;
    return json_decode(base64_decode($b),true);
}