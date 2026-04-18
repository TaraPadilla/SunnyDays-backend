<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Listar usuarios
     */
    public function index()
    {
        return User::all();
    }

    /**
     * Crear usuario
     */
    public function store(Request $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'perfil' => $request->perfil ?? 'operador',
        ]);

        return response()->json($user, 201);
    }

    /**
     * Ver usuario
     */
    public function show(string $id)
    {
        return User::findOrFail($id);
    }

    /**
     * Actualizar usuario
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $user->update([
            'name' => $request->name ?? $user->name,
            'email' => $request->email ?? $user->email,
            'perfil' => $request->perfil ?? $user->perfil,
            'password' => $request->password 
                ? Hash::make($request->password) 
                : $user->password,
        ]);

        return response()->json($user);
    }

    /**
     * Eliminar usuario
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'Usuario eliminado']);
    }
}