@extends('admin.layout')

@section('content')
    <livewire:admin.regional-discount-manager />
@endsection
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $discounts->links() }}</div>
@endsection
