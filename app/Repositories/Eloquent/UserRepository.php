<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use Illuminate\Http\Request;
use App\Repositories\Contracts\IUser;
use Grimzy\LaravelMysqlSpatial\Types\Point;
use App\Repositories\Eloquent\BaseRepository;

class UserRepository extends BaseRepository implements IUser
{
    public function model()
    {
        return User::class;  // App\Models\User
    }

    public function findByEmail($email)
    {
        return $this->model
                    ->where('email', $email)
                    ->first();
    }

    public function search(Request $request)
    {
        $query = (new $this->model)->newQuery();

        // Verified only
        $query->whereNotNull('email_verified_at');

        // Only designers who have designs
        if($request->has_designs){
            $query->has('designs');
        }

        // Check for available_to_hire
        if($request->available_to_hire){
            $query->where('available_to_hire', true);
        }

        // Geographic search
        $lat = $request->latitude;
        $lng = $request->longitude;
        $dist = $request->distance;
        $unit = $request->unit;

        if($lat && $lng){
            $point = new Point($lat, $lng);
            $unit == 'km' ? $dist *= 1000 : $dist *= 1609.34;
            $query->distanceSphereExcludingSelf('location', $point, $dist);
        }
        // Search name, username and tagline for provided string
        if($request->q){
            $query->where(function($q) use ($request){
                $q->where('name', 'like', '%'.$request->q.'%')
                    ->orWhere('tagline', 'like', '%'.$request->q.'%');
            });
        }

        // Order the results
        if($request->orderBy == 'closest'){
            $query->orderByDistanceSphere('location', $point, 'asc');
        }
       elseif($request->orderBy == 'az'){
            $query->orderBy('name');
        }elseif($request->orderBy == 'za'){
            $query->orderByDesc('name');
        }elseif($request->orderBy == 'latest'){
            $query->latest();
        } else{
            $query->oldest();
        }
        return $query->with('designs')->paginate(12, ['*'], 'page', $request->page);

    }
}