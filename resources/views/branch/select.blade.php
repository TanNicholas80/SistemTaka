{{-- resources/views/branch/select.blade.php --}}
@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\Storage;
@endphp

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Pilih Data Toko - Taka Textiles</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('lte/plugins/fontawesome-free/css/all.min.css') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo.svg') }}">
</head>

<body class="min-h-screen bg-gradient-to-br from-gray-50 to-blue-100 flex flex-col">
    {{-- HEADER --}}
    <header
        class="flex flex-col md:flex-row justify-between items-center px-4 md:px-6 py-3 border-b bg-white shadow-sm relative w-full">
        <div class="flex items-center gap-2 text-sm text-gray-600 mb-2 md:mb-0">
            <img src="{{ asset('images/ip.svg') }}" alt="IP Logo" class="h-5">
            <span>IP Saat ini: {{ request()->ip() }}</span>
            <span class="text-gray-400"> | </span>
            <span id="datetime" class="ml-1"></span>
        </div>
        <div class="md:absolute left-1/2 transform md:-translate-x-1/2">
            <img src="{{ asset('images/logopanjang.svg') }}" alt="Logo Toko" class="h-10 md:h-12">
        </div>
        <div class="flex items-center gap-3 md:gap-4 mt-2 md:mt-0">
            <div class="text-right text-sm hidden sm:block">
                <p class="font-medium">{{ Auth::user()->username }}</p>
                <p class="text-gray-500 text-xs capitalize">{{ str_replace('_', ' ', Auth::user()->role) }}</p>
            </div>
            <img src="{{ asset('images/av1.png') }}" alt="avatar"
                class="w-8 h-8 md:w-10 md:h-10 rounded-full object-cover">
            <ul class="navbar-nav ml-auto flex items-center" style="position: relative; display: flex;">
                <li class="nav-item list-none">
                    <a class="nav-link text-red-500 hover:text-red-600 flex items-center gap-1"
                        href="{{ route('logout') }}"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                @csrf
            </form>
        </div>
    </header>

    {{-- FLOATING FLASH MESSAGES --}}
    <div id="flash-message" class="fixed top-6 right-4 z-50 min-w-[200px] max-w-xs transition-all duration-300 hidden">
        <div id="flash-content"></div>
    </div>

    {{-- MAIN --}}
    <div class="flex flex-1 flex-col md:flex-row px-2 md:px-8 py-4 md:py-8 gap-4 md:gap-8 w-full"
        style="background: linear-gradient(135deg, #fff 0%, #b9c9ff 100%);">

        {{-- LEFT PROMO IMAGE --}}
        <aside
            class="w-full md:w-96 bg-white shadow-md rounded-2xl p-0 self-stretch mb-4 md:mb-0 flex-shrink-0 flex items-center justify-center">
            <img src="{{ asset('images/promo.jpg') }}" alt="Promo" class="w-full h-full object-cover rounded-2xl"
                style="min-height:160px; max-height:100%;">
        </aside>

        {{-- CONTENT --}}
        <main class="flex-1 w-full">
            <div
                class="flex flex-col sm:flex-row justify-between items-center mb-6 bg-white p-3 rounded gap-3 border-b-2 border-pink-500">
                <h2 class="text-md font-semibold text-gray-700 w-full sm:w-auto text-center sm:text-left">Data Toko</h2>

                <div class="flex items-center gap-3 w-full sm:w-auto justify-center sm:justify-end">
                    @if (Auth::user()->role === 'super_admin')
                        <button type="button" onclick="openCreateModal()"
                            class="w-8 h-8 bg-pink-500 text-white rounded flex items-center justify-center hover:bg-pink-600"
                            title="Tambah Toko">
                            <span class="text-lg font-bold">+</span>
                        </button>
                    @endif

                    <div class="relative w-full sm:w-auto">
                        <form method="GET" action="{{ route('branch.select') }}" class="w-full">
                            <input type="text" name="q" value="{{ request('q') }}"
                                placeholder="Cari Data Toko"
                                class="w-full sm:w-72 border rounded-lg px-3 py-2 focus:ring focus:ring-pink-300 text-sm">
                        </form>
                        <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"><i
                                class="fas fa-search"></i></span>
                    </div>
                </div>
            </div>

            {{-- GRID --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6 pl-0 md:pl-4">
                @foreach ($branches as $branch)
                    @if (!request('q') || Str::contains(Str::lower($branch->name), Str::lower(request('q'))))
                        <div class="h-full relative" data-branch-id="{{ $branch->id }}"
                            data-branch-name="{{ $branch->name }}" data-branch-photo="{{ $branch->photo ?? '' }}"
                            data-branch-customer="{{ $branch->customer_id ?? '' }}"
                            data-branch-url-accurate="{{ $branch->url_accurate ?? '' }}">

                            @if (Auth::user()->role === 'super_admin')
                                <div class="absolute top-2 right-2 z-30">
                                    <button type="button"
                                        onclick="event.stopPropagation(); toggleMenu('menu-{{ $branch->id }}')"
                                        class="text-gray-400 hover:text-gray-600 p-1 rounded-full hover:bg-gray-100 transition-colors">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>

                                    <div id="menu-{{ $branch->id }}"
                                        class="absolute right-0 mt-1 w-36 bg-white rounded-md shadow-lg py-1 hidden border border-gray-200 z-40">
                                        <button type="button"
                                            onclick="event.stopPropagation(); openEditModalFromData(event, this)"
                                            class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-edit mr-2"></i>Edit
                                        </button>
                                        <button type="button"
                                            onclick="event.stopPropagation(); openDeleteModalFromData(event, this)"
                                            class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                            <i class="fas fa-trash mr-2"></i>Hapus
                                        </button>
                                    </div>
                                </div>
                            @endif

                            {{-- form choose (klik card untuk memilih cabang) --}}
                            <form action="{{ route('branch.choose') }}" method="POST" class="h-full">
                                @csrf
                                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                <button type="submit"
                                    class="w-full h-full text-left bg-white shadow hover:shadow-lg transition rounded-lg overflow-hidden border border-gray-200 group cursor-pointer">
                                    <div class="flex flex-col items-center p-6">
                                        <div
                                            class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4 overflow-hidden">
                                            @if ($branch->photo)
                                                <img src="{{ $branch->photo ? Storage::url($branch->photo) : asset('images/default.png') }}"
                                                    alt="logo" class="w-full h-full object-cover">
                                            @else
                                                <span
                                                    class="text-lg font-bold uppercase">{{ Str::limit($branch->name, 4, '') }}</span>
                                            @endif
                                        </div>
                                        <span
                                            class="bg-green-100 text-green-600 text-xs font-medium px-3 py-1 rounded-full mb-2">COMMERCIAL</span>
                                    </div>

                                    <div
                                        class="block w-full bg-blue-600 group-hover:bg-blue-700 text-white text-center py-3 font-medium rounded-b-lg">
                                        {{ $branch->name }}
                                    </div>
                                </button>
                            </form>
                        </div>
                    @endif
                @endforeach
            </div>
        </main>
    </div>

    <!-- CREATE MODAL -->
    <div id="createModal"
        class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg w-full max-w-lg max-h-[90vh] overflow-y-auto shadow-lg">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Tambah Toko</h3>
                    <button type="button" onclick="closeCreateModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('branch.store') }}" enctype="multipart/form-data"
                    class="space-y-3">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nama Toko</label>
                        <input type="text" name="name" required class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Customer ID</label>
                        <input type="text" name="customer_id" required class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">URL Accurate</label>
                        <input type="text" name="url_accurate" placeholder="https://iris.accurate.id/accurate/api"
                            class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Foto</label>
                        <input type="file" name="photo" accept="image/*"
                            class="w-full border rounded px-3 py-2">
                        <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG (Max: 2MB)</p>
                    </div>

                    <div class="flex justify-end gap-3 mt-4">
                        <button type="button" onclick="closeCreateModal()"
                            class="px-4 py-2 border rounded">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-pink-500 text-white rounded">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- EDIT MODAL -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg w-full max-w-lg max-h-[90vh] overflow-y-auto shadow-lg">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Edit Toko</h3>
                    <button type="button" onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form id="editForm" method="POST" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nama Toko</label>
                        <input type="text" name="name" id="editName" required
                            class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Customer ID</label>
                        <input type="text" name="customer_id" id="editCustomerId" required
                            class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">URL Accurate</label>
                        <input type="text" name="url_accurate" id="editUrlAccurate"
                            placeholder="https://iris.accurate.id/accurate/api" class="w-full border rounded px-3 py-2">
                    </div>
                    <div id="currentLogo" class="mt-2"></div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Ganti Foto</label>
                        <input type="file" name="photo" accept="image/*"
                            class="w-full border rounded px-3 py-2">
                    </div>

                    <div class="flex justify-end gap-3 mt-4">
                        <button type="button" onclick="closeEditModal()"
                            class="px-4 py-2 border rounded">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-pink-500 text-white rounded">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- DELETE MODAL -->
    <div id="deleteModal"
        class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg w-full max-w-md shadow-lg">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-red-600">Hapus Toko</h3>
                    <button type="button" onclick="closeDeleteModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <p class="text-sm text-gray-700 mb-4">
                    Apakah Anda yakin ingin menghapus toko <span id="deleteBranchName"
                        class="font-semibold text-red-600"></span>?
                    Aksi ini tidak bisa dibatalkan.
                </p>

                <form id="deleteForm" method="POST" class="flex justify-end gap-3">
                    @csrf
                    @method('DELETE')
                    <button type="button" onclick="closeDeleteModal()"
                        class="px-4 py-2 border rounded">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded">Hapus</button>
                </form>
            </div>
        </div>
    </div>

    {{-- SCRIPTS --}}
    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector("i");
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }

        const baseStorageUrl = "{{ Storage::url('') }}";

        // Waktu
        function updateDateTime() {
            const now = new Date();
            const day = String(now.getDate()).padStart(2, '0');
            const monthName = now.toLocaleDateString('id-ID', {
                month: 'long'
            });
            const year = now.getFullYear();
            const hh = String(now.getHours()).padStart(2, '0');
            const mm = String(now.getMinutes()).padStart(2, '0');
            const ss = String(now.getSeconds()).padStart(2, '0');
            document.getElementById('datetime').textContent = `${day} ${monthName} ${year} - ${hh}:${mm}:${ss}`;
        }
        setInterval(updateDateTime, 1000);
        updateDateTime();

        function toggleMenu(id) {
            const el = document.getElementById(id);
            if (!el) return;
            el.classList.toggle('hidden');
            document.querySelectorAll('[id^="menu-"]').forEach(m => {
                if (m.id !== id) m.classList.add('hidden');
            });
        }
        document.addEventListener('click', function(e) {
            if (!e.target.closest('[id^="menu-"]') && !e.target.closest('.fa-ellipsis-v')) {
                document.querySelectorAll('[id^="menu-"]').forEach(m => m.classList.add('hidden'));
            }
        });

        // CREATE
        function openCreateModal() {
            document.getElementById('createModal').classList.remove('hidden');
        }

        function closeCreateModal() {
            document.getElementById('createModal').classList.add('hidden');
        }

        // EDIT
        function openEditModalFromData(e, buttonEl) {
            e.stopPropagation();
            const card = buttonEl.closest('[data-branch-id]');
            if (!card) return;
            openEditModal(
                card.getAttribute('data-branch-id'),
                card.getAttribute('data-branch-name'),
                card.getAttribute('data-branch-photo'),
                card.getAttribute('data-branch-customer') ?? '',
                card.getAttribute('data-branch-url-accurate') ?? ''
            );
        }

        function openEditModal(id, name, photo, customer, urlAccurate) {
            document.getElementById('editModal').classList.remove('hidden');
            const form = document.getElementById('editForm');
            form.action = `/branch/${id}`;

            document.getElementById('editName').value = name || '';
            document.getElementById('editCustomerId').value = customer || '';
            document.getElementById('editUrlAccurate').value = urlAccurate || '';

            const currentLogo = document.getElementById('currentLogo');
            if (photo && photo !== '') {
                currentLogo.innerHTML =
                    `<p class="text-sm text-gray-600">Foto saat ini:</p><img src="${baseStorageUrl}${photo}" class="w-16 h-16 rounded-full object-cover mt-1">`;
            } else {
                currentLogo.innerHTML = `<p class="text-sm text-gray-500">Tidak ada foto</p>`;
            }
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        // DELETE
        function openDeleteModalFromData(e, buttonEl) {
            e.stopPropagation();
            const card = buttonEl.closest('[data-branch-id]');
            if (!card) return;
            openDeleteModal(card.getAttribute('data-branch-id'), card.getAttribute('data-branch-name'));
        }

        function openDeleteModal(id, name) {
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteBranchName').textContent = name;
            document.getElementById('deleteForm').action = `/branch/${id}`;
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // FLASH
        function showFlashMessage(type, message) {
            const flash = document.getElementById('flash-message');
            const content = document.getElementById('flash-content');
            let color = 'bg-green-500';
            let icon = '<i class="fas fa-check-circle mr-2"></i>';
            if (type === 'error') {
                color = 'bg-red-500';
                icon = '<i class="fas fa-exclamation-circle mr-2"></i>';
            } else if (type === 'warning') {
                color = 'bg-yellow-500';
                icon = '<i class="fas fa-exclamation-triangle mr-2"></i>';
            }
            content.innerHTML =
                `<div class="${color} text-white px-4 py-3 rounded shadow flex items-center text-sm">${icon}${message}</div>`;
            flash.classList.remove('hidden');
            setTimeout(() => {
                flash.classList.add('hidden');
            }, 3500);
        }

        @if (session('success'))
            showFlashMessage('success', @json(session('success')));
        @endif
        @if (session('error'))
            showFlashMessage('error', @json(session('error')));
        @endif
        @if ($errors->any())
            showFlashMessage('warning', @json($errors->first()));
        @endif
    </script>

    <script src="https://kit.fontawesome.com/de9d16bb0f.js" crossorigin="anonymous"></script>
</body>

</html>
