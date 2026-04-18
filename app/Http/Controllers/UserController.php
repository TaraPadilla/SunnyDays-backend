<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Listar usuarios
     */
    public function index(): JsonResponse
    {
        $users = User::all();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Usuarios obtenidos correctamente',
            'data' => $users
        ], 200);
    }

    /**
     * Crear usuario
     */
    public function store(Request $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'perfil' => $request->perfil ?? 'operador',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Usuario creado correctamente',
            'data' => $user
        ], 201);
    }

    /**
     * Ver usuario
     */
    public function show(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Usuario obtenido correctamente',
            'data' => $user
        ], 200);
    }

    /**
     * Actualizar usuario
     */
    public function update(Request $request, string $id): JsonResponse
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

        return response()->json([
            'status' => 'success',
            'message' => 'Usuario actualizado correctamente',
            'data' => $user
        ], 200);
    }

    /**
     * Eliminar usuario
     */
    public function destroy(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Usuario eliminado correctamente'
        ], 200);
    }
}