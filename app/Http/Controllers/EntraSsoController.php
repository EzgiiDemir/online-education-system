<?php
namespace App\Http\Controllers;
use App\Models\PortalSetting; use App\Models\User; use Illuminate\Http\Request; use Illuminate\Support\Facades\Auth; use Illuminate\Support\Facades\Http; use Illuminate\Support\Str;
class EntraSsoController extends Controller {
    protected function redirectUri(PortalSetting $settings): string { return $settings->entra_redirect_uri ?: route('entra.callback'); }

    public function redirect(Request $r){
        $settings=PortalSetting::current();
        abort_unless($settings->entra_enabled && $settings->entra_tenant_id && $settings->entra_client_id,404);
        $state=Str::random(40); $r->session()->put('entra_state',$state);
        $params=['client_id'=>$settings->entra_client_id,'response_type'=>'code','redirect_uri'=>$this->redirectUri($settings),'response_mode'=>'query','scope'=>'openid profile email User.Read','state'=>$state];
        return redirect('https://login.microsoftonline.com/'.$settings->entra_tenant_id.'/oauth2/v2.0/authorize?'.http_build_query($params));
    }

    public function callback(Request $r){
        $settings=PortalSetting::current();
        abort_unless($settings->entra_enabled,404);
        $expectedState=$r->session()->pull('entra_state');
        abort_unless($expectedState && hash_equals($expectedState,(string)$r->query('state')),419,'Oturum doğrulaması başarısız, tekrar deneyin.');
        abort_if($r->filled('error'),422,$r->input('error_description','Microsoft girişi iptal edildi.'));
        $code=$r->query('code'); abort_unless($code,422,'Yetkilendirme kodu alınamadı.');

        $token=Http::asForm()->post('https://login.microsoftonline.com/'.$settings->entra_tenant_id.'/oauth2/v2.0/token',[
            'client_id'=>$settings->entra_client_id,'client_secret'=>$settings->entra_client_secret,
            'grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>$this->redirectUri($settings),
            'scope'=>'openid profile email User.Read',
        ]);
        abort_unless($token->successful(),502,'Microsoft ile token alışverişi başarısız.');

        $profile=Http::withToken($token->json('access_token'))->get('https://graph.microsoft.com/v1.0/me');
        abort_unless($profile->successful(),502,'Kullanıcı bilgisi alınamadı.');

        $email=strtolower($profile->json('mail') ?: (string)$profile->json('userPrincipalName'));
        abort_unless($email,422,'Microsoft hesabında e-posta bulunamadı.');
        $domain=substr(strrchr($email,'@'),1);
        abort_unless(in_array($domain,array_map('strtolower',$settings->accepted_domains ?: []),true),422,'Bu e-posta domaini kabul edilmiyor.');

        $user=User::firstOrCreate(['email'=>$email],['name'=>$profile->json('displayName') ?: $email,'password'=>Str::random(32),'role'=>'learner']);
        Auth::login($user); $r->session()->regenerate();
        if($user->role==='admin') return redirect('/admin');
        return redirect()->route($user->isInstructor()?'instructor.dashboard':'lms.home');
    }
}
