<?php

namespace App\Nova\Controller;

use Ramsey\Uuid\Uuid;
use Illuminate\Http\Request;
use App\Jobs\CreateTenantJob;
use App\Http\Controllers\Controller;
use App\Nova\Helpers\CloudflareHelper;
use App\Nova\Model\Tenant;

class RegisterDomain extends Controller
{
    public function showForm(Request $request){
        $url=$request->getHost();
        return inertia('central/RegisterDomain',compact('url'));
    }

    public function submit(Request $request)
    {
        $request->merge([
            'domain' => $request->get('domain') . '.' . $request->getHost()
        ]); 
        $data = $this->validate($request, [
            'domain' => 'required|string|unique:domains',
            'company' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:tenants',
            'password' => 'required|string|max:255',
            'subscription_expiry_date'=>'required'
        ]);

        $data['password'] = bcrypt($data['password']);
        $data['id']=Uuid::uuid4()->toString();

        $domain = $data['domain'];
        unset($data['domain']);
        
        CreateTenantJob::dispatch($data,$domain);
        // waiting for tenant data creation
        sleep(5);

        $tenant=Tenant::where('id',$data['id'])->first();
         
        $tenant->createDomain([
            'domain' => $domain,
        ]);
        if(env('CLOUDFLARE_ENABLED')){
            // create subdomain cname record on dns server
            CloudflareHelper::crate_cname_record($domain);
        }
        
        return redirect()->back()->with(['domain'=>$domain]);
    }

}
