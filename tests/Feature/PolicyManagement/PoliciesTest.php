<?php

use App\Models\PolicyManagement\Policy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\post;

beforeEach(function (){
    $this->followingRedirects();
});

it('cannot upload mulitple policies with same name', function (){
    $admin = loginWithRole();
    $data_scope = getScope($admin);

    $data = [
        'policy_file' => new UploadedFile(base_path('tests/dummy-files/pdf/dummy.pdf'), 'dummy.pdf', 'application/pdf', null, true),
        'display_name' => 'Policy 1',
        'version' => 'v1.0',
        'description' => 'policy 1 description'
    ];

    post(route('policy-management.policies.upload-policies',['data_scope' => $data_scope]),$data)->assertJson(['success' => true]);

    $data['policy_file'] = new UploadedFile(base_path('tests/dummy-files/pdf/dummy-1.pdf'), 'dummy.pdf', 'application/pdf', null, true);

    post(route('policy-management.policies.upload-policies',['data_scope' => $data_scope]),$data)->assertSessionHasErrors(['display_name' => 'This policy already exists.']);

    assertDatabaseCount('policy_policies', 1);
});

it('deletes files when policy is deleted', function () {
    $admin = loginWithRole();
    $data_scope = getScope($admin);

    $data = [
        'policy_file' => new UploadedFile(base_path('tests/dummy-files/pdf/dummy.pdf'), 'dummy.pdf', 'application/pdf', null, true),
        'display_name' => 'Policy 1',
        'version' => 'v1.0',
        'description' => 'policy 1 description'
    ];

    post(route('policy-management.policies.upload-policies',['data_scope' => $data_scope]),$data)->assertJson(['success' => true]);
    assertDatabaseCount('policy_policies', 1);

    $policy = Policy::first();
    $path = 'public/' . $policy->path;

    Storage::assertExists($path);

    $this->delete(route('policy-management.policies.delete-policies',['id' => $policy->id,'data_scope' => $data_scope]))->assertSessionHas('success', 'Policy deleted successfully.');

    Storage::assertMissing($path);
});