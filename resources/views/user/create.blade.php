@extends('layout.main')
@section('content')
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">User</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item active"><a>Tambah User</a></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <form action="{{ route('user.store') }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card card-primary">
                                <div class="card-header">
                                    <h3 class="card-title">Form Tambah User</h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>Nama</label>
                                        <input type="text" name="name" class="form-control" placeholder="Nama"
                                            required>
                                        @error('name')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="form-group">
                                        <label>Username</label>
                                        <input type="text" name="username" class="form-control" placeholder="Username"
                                            required>
                                        @error('username')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="form-group">
                                        <label>Password</label>
                                        <input type="password" name="password" class="form-control" placeholder="Password"
                                            required>
                                        @error('password')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="form-group">
                                        <label>Role</label>
                                        <select name="role" id="role" class="form-control" required>
                                            <option value="" disabled {{ old('role') ? '' : 'selected' }}>-- Pilih Role --</option>
                                            <option value="super_admin" {{ old('role') == 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                                            <option value="owner" {{ old('role') == 'owner' ? 'selected' : '' }}>Owner</option>
                                            <option value="kepala_toko" {{ old('role') == 'kepala_toko' ? 'selected' : '' }}>Kepala Toko</option>
                                            <option value="akunting" {{ old('role') == 'akunting' ? 'selected' : '' }}>Akunting</option>
                                            <option value="marketing" {{ old('role') == 'marketing' ? 'selected' : '' }}>Marketing</option>
                                        </select>
                                    </div>

                                    
                                    @if (Auth::user()->role === 'super_admin')
                                        <div id="accurateFields" class="border rounded p-3 bg-light">
                                            <h6 class="mb-3">Accurate Credentials</h6>
                                            <div class="form-group">
                                                <label>API Token Accurate</label>
                                                <textarea name="accurate_api_token" id="accurate_api_token" class="form-control" rows="2"
                                                    placeholder="Masukkan API Token Accurate">{{ old('accurate_api_token') }}</textarea>
                                                @error('accurate_api_token')
                                                    <small class="text-danger">{{ $message }}</small>
                                                @enderror
                                            </div>
                                            <div class="form-group mb-0">
                                                <label>Signature Secret</label>
                                                <textarea name="accurate_signature_secret" id="accurate_signature_secret" class="form-control" rows="2"
                                                    placeholder="Masukkan Signature Secret">{{ old('accurate_signature_secret') }}</textarea>
                                                @error('accurate_signature_secret')
                                                    <small class="text-danger">{{ $message }}</small>
                                                @enderror
                                            </div>
                                        </div>
                                    @endif

                                    <div class="form-group">
                                        <label>Toko</label>
                                        <select name="branches[]" class="form-control" multiple>
                                            @foreach ($branches as $branch)
                                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="card-footer text-right">
                                    <button type="submit" class="btn btn-primary">Submit</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <script>
        function updateTitle(pageTitle) {
            document.title = pageTitle;
        }

        document.title = "Create User";
    </script>
@endsection
