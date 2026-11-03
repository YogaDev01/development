{{-- Baris tabel Beneficiary. Dipakai halaman index (tab awal) dan endpoint
     rows() yang memuat tab lain secara lazy. Kolom No diberi nomor ulang
     oleh JS tiap tabel digambar. --}}
@foreach($beneficiaries as $beneficiary)
                            <tr data-bank="{{ $beneficiary->bank->name ?? '' }}"
                                data-status="{{ $beneficiary->status }}"
                                data-category="{{ $beneficiary->category }}">
                                <td>{{ $loop->iteration }}</td>
                                <td><strong>{{ $beneficiary->nickname }}</strong></td>
                                <td>{{ $beneficiary->complete_name }}</td>
                                <td>
                                    @php
                                        $catBadge = ['INTERNAL' => 'badge-primary', 'EXTERNAL' => 'badge-warning', 'BILLING' => 'badge-secondary'][$beneficiary->category] ?? 'badge-secondary';
                                    @endphp
                                    <span class="badge {{ $catBadge }} px-2 py-1">{{ $beneficiary->category }}</span>
                                </td>
                                <td>
                                    @if($beneficiary->pic)
                                        {{ $beneficiary->pic->pic }}
                                        @if($beneficiary->pic->position)
                                            <div class="text-muted" style="font-size:11px;">{{ $beneficiary->pic->position }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-info px-2 py-1">
                                        {{ $beneficiary->bank->name ?? '-' }}
                                    </span>
                                </td>
                                <td>{{ $beneficiary->account_number ?? '-' }}</td>
                                <td>{{ $beneficiary->contact ?? '-' }}</td>
                                <td>
                                    @if($beneficiary->status == 'ACTIVE')
                                        <span class="badge badge-success px-2 py-1">ACTIVE</span>
                                    @else
                                        <span class="badge badge-danger px-2 py-1">INACTIVE</span>
                                    @endif
                                </td>
                                {{-- Kolomnya tetap dirender walau kosong: columnDefs DataTable
                                     di bawah menunjuk kolom terakhir lewat targets:[-1], jadi
                                     jumlah kolom <td> harus sama untuk setiap role. --}}
                                <td class="text-center action-column">
                                    @if($canManage)
                                    <div class="action-buttons ts-actions">

                                        <button type="button"
                                                class="ts-action-primary is-neutral btn-edit"
                                                data-id="{{ $beneficiary->id }}"
                                                title="Edit" aria-label="Edit {{ $beneficiary->name }}">
                                            <i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span>
                                        </button>

                                        {{-- Hapus ditampilkan langsung, bukan disembunyikan di
                                             balik menu "aksi lain": isinya memang cuma satu
                                             perintah, jadi menunya hanya menambah satu klik
                                             tanpa menyembunyikan apa pun. --}}
                                        <form action="{{ url('/beneficiaries/' . $beneficiary->id) }}"
                                              method="POST" class="d-inline delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ts-action-primary is-danger"
                                                    title="Hapus" aria-label="Hapus {{ $beneficiary->nickname }}">
                                                <i class="fas fa-trash" aria-hidden="true"></i><span class="ts-al">Hapus</span>
                                            </button>
                                        </form>

                                    </div>
                                    @else
                                    <span class="text-muted" title="Hanya Superadmin yang bisa mengubah atau menghapus">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
@endforeach
