<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ChatbotKnowledge;
use App\Models\ChatbotSetting;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    // ─── Initialise les settings si la table est vide ─────────
    private function getOrCreateSettings(): ChatbotSetting
    {
        $setting = ChatbotSetting::first();
        if (! $setting) {
            $setting = ChatbotSetting::create([
                'is_active'       => true,
                'welcome_message' => "Bonjour ! Je suis l'assistant virtuel de GCF Madagascar. Comment puis-je vous aider ?",
            ]);
        }
        return $setting;
    }

    // ─── PUBLIC : message du visiteur ─────────────────────────
    public function message(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        $settings = $this->getOrCreateSettings();

        if (! $settings->is_active) {
            return response()->json([
                'response' => 'Le chatbot est temporairement indisponible. Veuillez nous contacter directement par email.',
                'type'     => 'unavailable',
            ]);
        }

        $message  = mb_strtolower(trim($request->string('message')));
        $response = $this->processMessage($message);

        return response()->json([
            'response' => $response['text'],
            'type'     => $response['type'],
            'data'     => $response['data'] ?? null,
        ]);
    }

    // ─── PUBLIC : settings pour le widget (sans token) ────────
    // Retourne uniquement is_active + welcome_message, sans données sensibles
    public function publicSettings(): JsonResponse
    {
        $s = $this->getOrCreateSettings();

        return response()->json([
            'is_active'       => $s->is_active,
            'welcome_message' => $s->welcome_message,
        ]);
    }

    // ─── Traitement du message ────────────────────────────────
    private function processMessage(string $message): array
    {
        // 1. Base de connaissances admin
        $knowledgeBase = ChatbotKnowledge::where('is_active', true)->get();

        foreach ($knowledgeBase as $item) {
            $keywords = array_map('trim', explode(',', mb_strtolower($item->keywords)));
            foreach ($keywords as $keyword) {
                if ($keyword && str_contains($message, $keyword)) {
                    return ['text' => $item->response, 'type' => 'knowledge'];
                }
            }
        }

        // 2. Réponses contextuelles
        if ($this->matches($message, ['bonjour', 'salut', 'hello', 'bonsoir', 'hi', 'bon matin'])) {
            return ['text' => "Bonjour ! Je suis l'assistant virtuel de GCF Madagascar. Je peux vous informer sur nos projets climatiques, nos financements, nos zones d'intervention ou notre organisation. Comment puis-je vous aider ?", 'type' => 'greeting'];
        }

        if ($this->matches($message, ['projet', 'projets', 'programme', 'initiative'])) {
            $count  = Project::where('is_published', true)->count();
            $actifs = Project::where('statut', 'actif')->where('is_published', true)->count();
            return [
                'text' => "GCF Madagascar gère actuellement {$count} projets climatiques publiés, dont {$actifs} en cours d'implémentation. Ces projets couvrent des secteurs variés : adaptation côtière, biodiversité, gestion de l'eau, forêt, énergie renouvelable et agriculture résiliente. Consultez notre carte interactive pour explorer les projets par région.",
                'type' => 'projects',
            ];
        }

        if ($this->matches($message, ['financement', 'budget', 'fonds', 'argent', 'coût', 'montant'])) {
            return ['text' => "GCF Madagascar mobilise des financements diversifiés : dons, prêts, subventions et cofinancements. Les sources incluent le Green Climate Fund international, des partenaires bilatéraux (AFD, coopération japonaise…), le PNUD et des organismes multilatéraux. Tous les détails financiers sont transparents et accessibles sur notre plateforme.", 'type' => 'finance'];
        }

        if ($this->matches($message, ['gcf', 'organisation', 'qui sommes', 'présentation', 'mission', 'mandat'])) {
            return ['text' => "Le Green Climate Fund (GCF) Madagascar est l'entité nationale de coordination des financements climatiques internationaux. Notre mission : aider Madagascar à s'adapter aux changements climatiques et réduire ses émissions de gaz à effet de serre, en favorisant un développement durable, inclusif et résilient pour toutes les régions du pays.", 'type' => 'about'];
        }

        if ($this->matches($message, ['contact', 'adresse', 'email', 'courriel', 'téléphone', 'joindre', 'bureau'])) {
            return ['text' => "Pour nous contacter : utilisez le formulaire de contact sur ce site, ou écrivez-nous à info@gcf-madagascar.org. Notre bureau est basé à Antananarivo. Nous sommes disponibles du lundi au vendredi, 8h–17h (GMT+3). Nous répondons dans les 48 heures ouvrables.", 'type' => 'contact'];
        }

        if ($this->matches($message, ['madagascar', 'région', 'zone', 'carte', 'province', 'localisation', 'géographique'])) {
            return ['text' => "Madagascar est divisée en 6 provinces et 22 régions. GCF Madagascar intervient dans plusieurs régions prioritaires, notamment les zones côtières vulnérables, les hautes terres centrales et les zones forestières de l'est. Consultez notre carte interactive pour visualiser la distribution géographique de tous nos projets.", 'type' => 'geo'];
        }

        if ($this->matches($message, ['rapport', 'document', 'publication', 'rapport annuel'])) {
            return ['text' => "Nos rapports de projets, études d'impact et documents officiels sont disponibles dans la section documents de chaque projet sur notre plateforme. Vous pouvez les télécharger directement ou nous contacter pour des rapports spécifiques.", 'type' => 'documents'];
        }

        if ($this->matches($message, ['merci', 'super', 'parfait', 'ok', 'bien', 'excellent', 'génial'])) {
            return ['text' => "Avec plaisir ! N'hésitez pas si vous avez d'autres questions sur GCF Madagascar ou nos projets climatiques. Bonne journée !", 'type' => 'thanks'];
        }

        return [
            'text' => "Je ne suis pas certain de comprendre votre question. Je peux vous informer sur : nos **projets climatiques**, nos **financements**, nos **zones d'intervention**, ou comment nous **contacter**. Que souhaitez-vous savoir ?",
            'type' => 'default',
        ];
    }

    private function matches(string $message, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (str_contains($message, $kw)) return true;
        }
        return false;
    }

    // ─── ADMIN — Settings ─────────────────────────────────────
    public function settings(): JsonResponse
    {
        return response()->json($this->getOrCreateSettings());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'is_active'       => ['sometimes'],
            'welcome_message' => ['nullable', 'string', 'max:500'],
        ]);

        $data = [];
        if ($request->has('is_active')) {
            $data['is_active'] = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
        }
        if ($request->has('welcome_message')) {
            $data['welcome_message'] = $request->input('welcome_message') ?? '';
        }

        $s = $this->getOrCreateSettings();
        $s->update($data);

        return response()->json($s);
    }

    // ─── ADMIN — Knowledge base ───────────────────────────────
    public function knowledge(): JsonResponse
    {
        return response()->json(ChatbotKnowledge::orderBy('category')->get());
    }

    public function storeKnowledge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category'  => ['required', 'string', 'max:100'],
            'keywords'  => ['required', 'string'],
            'response'  => ['required', 'string'],
        ]);

        $validated['is_active'] = filter_var($request->input('is_active', true), FILTER_VALIDATE_BOOLEAN);

        return response()->json(ChatbotKnowledge::create($validated), 201);
    }

    public function updateKnowledge(Request $request, int $id): JsonResponse
    {
        $item = ChatbotKnowledge::findOrFail($id);

        $data = $request->validate([
            'category'  => ['sometimes', 'string', 'max:100'],
            'keywords'  => ['sometimes', 'string'],
            'response'  => ['sometimes', 'string'],
        ]);

        if ($request->has('is_active')) {
            $data['is_active'] = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
        }

        $item->update($data);
        return response()->json($item);
    }

    public function destroyKnowledge(int $id): JsonResponse
    {
        ChatbotKnowledge::findOrFail($id)->delete();
        return response()->json(['message' => 'Entrée supprimée.']);
    }
}