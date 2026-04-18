<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Listar usuarios
     */
    public function index(): JsonResponse
    {
        Log::info('[UserController] index: petición recibida');
        
        $users = User::all();
        
        Log::info('[UserController] index: éxito', ['total' => $users->count()]);
        
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
        Log::info('[UserController] store: petición recibida', [
            'data' => $request->all()
        ]);
        
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'perfil' => $request->perfil ?? 'operador',
        ]);
        
        Log::info('[UserController] store: usuario creado', [
            'user_id' => $user->id,
            'email' => $user->email,
            'perfil' => $user->perfil
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
        Log::info('[UserController] show: petición recibida', ['id' => $id]);
        
        $user = User::findOrFail($id);
        
        Log::info('[UserController] show: éxito', ['user_id' => $user->id]);
        
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
        Log::info('[UserController] update: petición recibida', [
            'id' => $id,
            'data' => $request->all()
        ]);
        
        $user = User::findOrFail($id);

        $user->update([
            'name' => $request->name ?? $user->name,
            'email' => $request->email ?? $user->email,
            'perfil' => $request->perfil ?? $user->perfil,
            'password' => $request->password 
                ? Hash::make($request->password) 
                : $user->password,
        ]);
        
        Log::info('[UserController] update: éxito', ['user_id' => $user->id]);

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
        Log::info('[UserController] destroy: petición recibida', ['id' => $id]);
        
        $user = User::findOrFail($id);
        $user->delete();
        
        Log::info('[UserController] destroy: éxito', ['user_id' => $user->id]);

        return response()->json([
            'status' => 'success',
            'message' => 'Usuario eliminado correctamente'
        ], 200);
    }
}