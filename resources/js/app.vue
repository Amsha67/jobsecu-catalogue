<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <header class="bg-blue-800 text-white px-8 py-5 shadow">
            <h1 class="text-2xl font-bold">Jobsecu — Import Catalogue EPI</h1>
            <p class="text-blue-200 text-sm mt-1">Glissez vos fiches techniques PDF pour remplir automatiquement Google
                Sheets</p>
        </header>

        <main class="max-w-4xl mx-auto px-6 py-8">

            <!-- Sélection onglet -->
            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Onglet Google Sheets cible</label>
                <select v-model="onglet"
                    class="border border-gray-300 rounded-lg px-4 py-2 w-full max-w-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="Produits Pieds">Produits Pieds</option>
                    <option value="Produits Têtes">Produits Têtes</option>
                    <option value="Produits Auditif">Produits Auditif</option>
                    <option value="Produits Yeux">Produits Yeux</option>
                    <option value="Produits Masques">Produits Masques</option>
                    <option value="Produits Mains">Produits Mains</option>
                    <option value="Produits Corps">Produits Corps</option>
                    <option value="Produits Anti-chutes">Produits Anti-chutes</option>
                    <option value="Produits Couteaux">Produits Couteaux</option>
                </select>
            </div>

            <!-- Zone de dépôt -->
            <div class="border-4 border-dashed rounded-2xl p-12 text-center transition-colors duration-200 cursor-pointer"
                :class="dragging ? 'border-blue-500 bg-blue-50' : 'border-gray-300 bg-white hover:border-blue-400'"
                @dragover.prevent="dragging = true" @dragleave="dragging = false" @drop.prevent="onDrop"
                @click="$refs.fileInput.click()">
                <div class="text-6xl mb-4">📄</div>
                <p class="text-xl font-semibold text-gray-600">Glissez vos PDFs ici</p>
                <p class="text-gray-400 mt-2">ou cliquez pour sélectionner des fichiers</p>
                <p class="text-sm text-gray-400 mt-1">Plusieurs fichiers acceptés</p>
                <input ref="fileInput" type="file" accept=".pdf" multiple class="hidden" @change="onFileSelect" />
            </div>

            <!-- File en attente -->
            <div v-if="files.length > 0 && !traitement" class="mt-6">
                <h2 class="font-semibold text-gray-700 mb-3">{{ files.length }} fichier(s) en attente</h2>
                <div class="space-y-2 mb-4">
                    <div v-for="(f, i) in files" :key="i"
                        class="flex items-center justify-between bg-white border rounded-lg px-4 py-3">
                        <span class="text-sm text-gray-600">📄 {{ f.name }}</span>
                        <span class="text-xs text-gray-400">{{ Math.round(f.size / 1024) }} Ko</span>
                    </div>
                </div>
                <button @click="traiterTout"
                    class="w-full bg-blue-700 hover:bg-blue-800 text-white font-semibold py-3 rounded-xl transition">
                    🚀 Lancer l'import ({{ files.length }} PDF)
                </button>
            </div>

            <!-- Barre de progression -->
            <div v-if="traitement" class="mt-6 bg-white border rounded-2xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <span class="font-semibold text-gray-700">Traitement en cours...</span>
                    <span class="text-sm text-gray-500">{{ progression }} / {{ total }} fichiers</span>
                </div>

                <!-- Barre principale -->
                <div class="w-full bg-gray-200 rounded-full h-4 mb-4 overflow-hidden">
                    <div class="h-4 rounded-full transition-all duration-500"
                        :class="progression === total ? 'bg-green-500' : 'bg-blue-600'"
                        :style="{ width: pourcentage + '%' }"></div>
                </div>

                <p class="text-sm text-gray-500 text-center">
                    {{ progression === total ? '✅ Terminé !' : '⏳ ' + fichierEnCours }}
                </p>

                <!-- Mini résultats en temps réel -->
                <div v-if="resultats.length > 0" class="mt-4 space-y-2 max-h-48 overflow-y-auto">
                    <div v-for="(r, i) in resultats" :key="i"
                        class="flex items-center justify-between text-sm px-3 py-2 rounded-lg"
                        :class="r.erreur ? 'bg-red-50' : 'bg-green-50'">
                        <span class="font-medium">{{ r.nom }}</span>
                        <span :class="r.erreur ? 'text-red-600' : 'text-green-600'">
                            {{ r.erreur ? '❌' : r.statut === 'mis_a_jour' ? '✅ Mis à jour' : '➕ Ajouté' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Résultats finaux -->
            <div v-if="!traitement && resultats.length > 0" class="mt-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold text-gray-700">
                        Résultats — {{ succes }} succès / {{ echecs }} erreur(s)
                    </h2>
                    <button @click="reinitialiser" class="text-sm text-blue-600 hover:underline">
                        ↺ Nouveau lot
                    </button>
                </div>

                <div class="space-y-3">
                    <div v-for="(r, i) in resultats" :key="i" class="bg-white border rounded-xl px-5 py-4 shadow-sm"
                        :class="r.erreur ? 'border-red-300' : 'border-green-300'">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-semibold">{{ r.nom }}</span>
                                <span v-if="r.nom_woocommerce" class="text-sm text-gray-500 ml-2">→ {{ r.nom_woocommerce
                                    }}</span>
                            </div>
                            <span class="text-xs font-semibold px-3 py-1 rounded-full"
                                :class="r.erreur ? 'bg-red-100 text-red-700' : r.statut === 'mis_a_jour' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'">
                                {{ r.erreur ? '❌ Erreur' : r.statut === 'mis_a_jour' ? '✅ Mis à jour' : '➕ Ajouté' }}
                            </span>
                        </div>
                        <div v-if="r.alertes && r.alertes.length > 0" class="mt-2 flex flex-wrap gap-1">
                            <span v-for="(a, j) in r.alertes" :key="j"
                                class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded">
                                ⚠️ {{ a }}
                            </span>
                        </div>
                        <p v-if="r.erreur" class="text-sm text-red-600 mt-1">{{ r.erreur }}</p>
                    </div>
                </div>
            </div>

        </main>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const onglet = ref('Produits Pieds')
const files = ref([])
const dragging = ref(false)
const traitement = ref(false)
const resultats = ref([])
const progression = ref(0)
const total = ref(0)
const fichierEnCours = ref('')

const pourcentage = computed(() =>
    total.value === 0 ? 0 : Math.round((progression.value / total.value) * 100)
)

const succes = computed(() => resultats.value.filter(r => !r.erreur).length)
const echecs = computed(() => resultats.value.filter(r => r.erreur).length)

function onDrop(e) {
    dragging.value = false
    const dropped = Array.from(e.dataTransfer.files).filter(f => f.type === 'application/pdf')
    files.value = [...files.value, ...dropped]
}

function onFileSelect(e) {
    files.value = [...files.value, ...Array.from(e.target.files)]
}

function reinitialiser() {
    resultats.value = []
    progression.value = 0
    total.value = 0
    fichierEnCours.value = ''
}

async function traiterTout() {
    traitement.value = true
    resultats.value = []
    total.value = files.value.length
    progression.value = 0

    for (const f of files.value) {
        fichierEnCours.value = f.name
        const formData = new FormData()
        formData.append('pdf', f)
        formData.append('onglet', onglet.value)

        try {
            const res = await fetch('/api/traiter-pdf', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: formData,
            })
            const data = await res.json()

            if (data.succes) {
                resultats.value.push({
                    nom: data.produit.nom,
                    nom_woocommerce: data.produit.nom_woocommerce,
                    statut: data.statut,
                    alertes: data.produit.alertes,
                })
            } else {
                resultats.value.push({ nom: f.name, erreur: data.erreur })
            }
        } catch (err) {
            resultats.value.push({ nom: f.name, erreur: err.message })
        }

        progression.value++
    }

    files.value = []
    traitement.value = false
}
</script>