
#include <iostream>
#include <fstream>
#include <string>
#include <nlohmann/json.hpp>

using json = nlohmann::json;

int main() {
    // Legge il JSON
    std::ifstream input("input.json");
    if (!input.is_open()) {
        std::cerr << "Errore: impossibile aprire input.json\n";
        return 1;
    }

    json j;
    try {
        input >> j;
    } catch (const json::parse_error& e) {
        std::cerr << "JSON non valido: " << e.what() << '\n';
        return 1;
    }

    // Verifica i campi
    if (!j.contains("content") || !j["content"].is_string() ||
        !j.contains("path") || !j["path"].is_string()) {
        std::cerr << "Errore: servono i campi stringa "
                     "\"content\" e \"path\".\n";
        return 1;
    }

    const std::string content = j["content"].get<std::string>();
    const std::string path = j["path"].get<std::string>();

    // Scrive il contenuto nel file senza ulteriori trasformazioni
    std::ofstream output(path, std::ios::binary);
    if (!output.is_open()) {
        std::cerr << "Errore: impossibile aprire " << path << '\n';
        return 1;
    }

    output.write(content.data(),
                 static_cast<std::streamsize>(content.size()));

    if (!output) {
        std::cerr << "Errore durante la scrittura del file.\n";
        return 1;
    }

    std::cout << "File scritto correttamente: " << path << '\n';
    return 0;
}

