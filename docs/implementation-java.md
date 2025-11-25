# Implémentation du client lourd Java (JavaFX) — Guide pas à pas pour débutants

Ce guide détaille, étape par étape, comment créer un client lourd Java (JavaFX) pour le projet « Coffre‑fort numérique ». Il complète le document `frontend.md` et s’aligne sur l’OpenAPI (`openapi.yaml`). Il vise des étudiants débutants en Java : chaque étape est explicitée avec des exemples concrets.

Important : côté client, vous ne ré-implémentez pas la logique métier. Votre application appelle l’API REST décrite dans `openapi.yaml`, gère l’authentification, les écrans JavaFX et l’expérience utilisateur (progression, messages d’erreur, etc.).

---

## 1) Pré-requis
- Java Development Kit (JDK) 17 ou plus (Temurin/Adoptium recommandé).
- Un IDE : IntelliJ IDEA Community (gratuit) ou Eclipse. IntelliJ est recommandé pour JavaFX.
- Git installé (facultatif mais utile).
- JavaFX 17+ (via dépendances Maven/Gradle, ne téléchargez pas manuellement le SDK si vous utilisez Maven/Gradle).
- Connexion au backend (URL du serveur) – voir `openapi.yaml`.

Astuce : vérifiez Java en ligne de commande
```
java -version
```
Vous devriez voir une version 17 ou supérieure.

---

## 2) Créer le projet

Vous pouvez utiliser Maven (plus simple pour débuter) ou Gradle. Choisissez UNE option.

### Option A — Projet Maven
1. Dans IntelliJ : New Project > Maven > cochez « Create from archetype » si vous voulez, sinon vide.
2. GroupId : `com.votreecole.coffrefort` ; ArtifactId : `client-javafx` ; Language : Java ; JDK : 17.
3. Remplacez le contenu du `pom.xml` par l’exemple ci‑dessous (adaptez `groupId`/`artifactId`).

`pom.xml` minimal (JavaFX + HTTP + JSON) :
```xml
<project xmlns="http://maven.apache.org/POM/4.0.0" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:schemaLocation="http://maven.apache.org/POM/4.0.0 http://maven.apache.org/xsd/maven-4.0.0.xsd">
  <modelVersion>4.0.0</modelVersion>
  <groupId>com.votreecole.coffrefort</groupId>
  <artifactId>client-javafx</artifactId>
  <version>1.0.0</version>

  <properties>
    <maven.compiler.source>17</maven.compiler.source>
    <maven.compiler.target>17</maven.compiler.target>
    <javafx.version>21.0.2</javafx.version>
    <jackson.version>2.17.2</jackson.version>
    <okhttp.version>4.12.0</okhttp.version>
  </properties>

  <dependencies>
    <!-- JavaFX -->
    <dependency>
      <groupId>org.openjfx</groupId>
      <artifactId>javafx-controls</artifactId>
      <version>${javafx.version}</version>
    </dependency>
    <dependency>
      <groupId>org.openjfx</groupId>
      <artifactId>javafx-fxml</artifactId>
      <version>${javafx.version}</version>
    </dependency>

    <!-- JSON: Jackson -->
    <dependency>
      <groupId>com.fasterxml.jackson.core</groupId>
      <artifactId>jackson-databind</artifactId>
      <version>${jackson.version}</version>
    </dependency>
    <dependency>
      <groupId>com.fasterxml.jackson.core</groupId>
      <artifactId>jackson-annotations</artifactId>
      <version>${jackson.version}</version>
    </dependency>

    <!-- HTTP client pratique pour multipart upload -->
    <dependency>
      <groupId>com.squareup.okhttp3</groupId>
      <artifactId>okhttp</artifactId>
      <version>${okhttp.version}</version>
    </dependency>
  </dependencies>

  <build>
    <plugins>
      <!-- Pour exécuter l’app avec: mvn javafx:run -->
      <plugin>
        <groupId>org.openjfx</groupId>
        <artifactId>javafx-maven-plugin</artifactId>
        <version>0.0.8</version>
        <configuration>
          <mainClass>com.votreecole.coffrefort.App</mainClass>
        </configuration>
      </plugin>
    </plugins>
  </build>
</project>
```

Commande utile pour lancer :
```
mvn clean javafx:run
```

### Option B — Projet Gradle (Kotlin DSL)
1. New Project > Gradle > Java.
2. Remplacez `build.gradle.kts` par :
```kotlin
plugins {
    application
}

repositories { mavenCentral() }

val javafxVersion = "21.0.2"
val jacksonVersion = "2.17.2"
val okhttpVersion = "4.12.0"

dependencies {
    implementation("org.openjfx:javafx-controls:$javafxVersion")
    implementation("org.openjfx:javafx-fxml:$javafxVersion")
    implementation("com.fasterxml.jackson.core:jackson-databind:$jacksonVersion")
    implementation("com.fasterxml.jackson.core:jackson-annotations:$jacksonVersion")
    implementation("com.squareup.okhttp3:okhttp:$okhttpVersion")
}

application {
    mainClass.set("com.votreecole.coffrefort.App")
}

tasks.register<JavaExec>("runApp") {
    mainClass.set("com.votreecole.coffrefort.App")
    classpath = sourceSets["main"].runtimeClasspath
}
```

Lancer :
```
./gradlew runApp
```
Sous Windows PowerShell :
```
./gradlew.bat runApp
```

---

## 3) Organisation du code (packages)
Reprenez la suggestion d’architecture de `frontend.md` :
- `com.votreecole.coffrefort.api` : client API, modèles de requêtes/réponses, gestion des erreurs HTTP.
- `com.votreecole.coffrefort.auth` : gestion des tokens/sessions.
- `com.votreecole.coffrefort.store` : modèles métier côté client (User, Folder, FileItem, Share, Version, Quota).
- `com.votreecole.coffrefort.view` : écrans JavaFX (FXML + contrôleurs).
- `com.votreecole.coffrefort.service` : services d’UX (upload avec progression, pagination, annulation).
- `com.votreecole.coffrefort.util` : utilitaires (formatage tailles, dates, validations, clipboard).
- `com.votreecole.coffrefort` : classe `App` (entrée principale JavaFX).

Arborescence recommandée :
```
src
 └─ main
    ├─ java
    │   └─ com/votreecole/coffrefort/...
    └─ resources
        └─ com/votreecole/coffrefort/view/*.fxml
```

---

## 4) Démarrer une application JavaFX minimale
Classe principale `App` :
```java
package com.votreecole.coffrefort;

import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.stage.Stage;

public class App extends Application {
    @Override
    public void start(Stage stage) throws Exception {
        Parent root = FXMLLoader.load(getClass().getResource("/com/votreecole/coffrefort/view/LoginView.fxml"));
        Scene scene = new Scene(root, 960, 640);
        stage.setTitle("Coffre-fort numérique");
        stage.setScene(scene);
        stage.show();
    }

    public static void main(String[] args) {
        launch(args);
    }
}
```

Premier écran FXML `LoginView.fxml` (dans `src/main/resources/com/votreecole/coffrefort/view/`) :
```xml
<?xml version="1.0" encoding="UTF-8"?>
<?import javafx.geometry.Insets?>
<?import javafx.scene.control.*?>
<?import javafx.scene.layout.*?>

<BorderPane xmlns:fx="http://javafx.com/fxml" fx:controller="com.votreecole.coffrefort.view.LoginController">
    <center>
        <VBox alignment="CENTER" spacing="12.0">
            <padding><Insets top="24" right="24" bottom="24" left="24"/></padding>
            <Label text="Connexion" style="-fx-font-size: 18px; -fx-font-weight: bold;"/>
            <TextField fx:id="serverUrlField" promptText="URL du serveur (ex: http://localhost:8080)"/>
            <TextField fx:id="emailField" promptText="Email / Identifiant"/>
            <PasswordField fx:id="passwordField" promptText="Mot de passe"/>
            <Button text="Se connecter" onAction="#onLogin"/>
            <Label fx:id="messageLabel" textFill="red"/>
        </VBox>
    </center>
</BorderPane>
```

Contrôleur `LoginController` :
```java
package com.votreecole.coffrefort.view;

import com.votreecole.coffrefort.api.ApiClient;
import com.votreecole.coffrefort.auth.SessionManager;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.scene.control.*;

public class LoginController {
    @FXML private TextField serverUrlField;
    @FXML private TextField emailField;
    @FXML private PasswordField passwordField;
    @FXML private Label messageLabel;

    private final ApiClient api = ApiClient.getInstance();
    private final SessionManager session = SessionManager.getInstance();

    @FXML
    public void initialize() {
        serverUrlField.setText("http://localhost:8080"); // valeur par défaut
    }

    @FXML
    private void onLogin() {
        messageLabel.setText("");
        String url = serverUrlField.getText().trim();
        String email = emailField.getText().trim();
        String password = passwordField.getText();

        // Exécuter l’appel réseau hors du thread UI
        new Thread(() -> {
            try {
                api.setBaseUrl(url);
                String token = api.login(email, password);
                session.saveToken(token);
                Platform.runLater(() -> messageLabel.setText("Connecté ! Passez à l’écran suivant."));
                // TODO: Charger la scène "Dashboard" ou "Explorer"
            } catch (Exception ex) {
                Platform.runLater(() -> messageLabel.setText("Erreur: " + ex.getMessage()));
            }
        }).start();
    }
}
```

---

## 5) Implémenter le client API

Vous pouvez utiliser `java.net.http.HttpClient` ou OkHttp. Pour gérer facilement l’upload multipart, OkHttp est plus simple. Exemple minimal d’un client API singleton :

```java
package com.votreecole.coffrefort.api;

import com.fasterxml.jackson.databind.DeserializationFeature;
import com.fasterxml.jackson.databind.ObjectMapper;
import okhttp3.*;

import java.io.File;
import java.io.IOException;

public class ApiClient {
    private static final ApiClient INSTANCE = new ApiClient();
    public static ApiClient getInstance() { return INSTANCE; }

    private final OkHttpClient http = new OkHttpClient();
    private final ObjectMapper json = new ObjectMapper()
            .configure(DeserializationFeature.FAIL_ON_UNKNOWN_PROPERTIES, false);

    private String baseUrl = "http://localhost:8080";
    private String bearerToken; // géré par SessionManager en pratique

    public void setBaseUrl(String url) { this.baseUrl = url; }
    public void setBearerToken(String token) { this.bearerToken = token; }

    // Exemple: POST /auth/login -> renvoie un token (adapter au openapi.yaml)
    public String login(String email, String password) throws IOException {
        RequestBody body = new FormBody.Builder()
                .add("email", email)
                .add("password", password)
                .build();
        Request req = new Request.Builder()
                .url(baseUrl + "/auth/login")
                .post(body)
                .build();
        try (Response resp = http.newCall(req).execute()) {
            if (!resp.isSuccessful()) {
                throw new IOException("HTTP " + resp.code());
            }
            // Selon l’API: { "token": "..." }
            var node = json.readTree(resp.body().byteStream());
            return node.get("token").asText();
        }
    }

    // Exemple: GET /me/quota
    public QuotaResponse getQuota() throws IOException {
        Request req = new Request.Builder()
                .url(baseUrl + "/me/quota")
                .header("Authorization", "Bearer " + bearerToken)
                .get()
                .build();
        try (Response resp = http.newCall(req).execute()) {
            if (!resp.isSuccessful()) throw httpError(resp);
            return json.readValue(resp.body().byteStream(), QuotaResponse.class);
        }
    }

    // Exemple: upload multipart
    public void uploadFile(String folderId, File file) throws IOException {
        MediaType octet = MediaType.parse("application/octet-stream");
        RequestBody fileBody = RequestBody.create(file, octet);
        MultipartBody requestBody = new MultipartBody.Builder()
                .setType(MultipartBody.FORM)
                .addFormDataPart("folderId", folderId)
                .addFormDataPart("file", file.getName(), fileBody)
                .build();
        Request req = new Request.Builder()
                .url(baseUrl + "/files/upload")
                .header("Authorization", "Bearer " + bearerToken)
                .post(requestBody)
                .build();
        try (Response resp = http.newCall(req).execute()) {
            if (!resp.isSuccessful()) throw httpError(resp);
        }
    }

    private IOException httpError(Response resp) {
        String msg = "HTTP " + resp.code();
        try {
            if (resp.body() != null) {
                msg += ": " + resp.body().string();
            }
        } catch (IOException ignore) {}
        return new IOException(msg);
    }

    // DTO d’exemple (adaptez aux schémas de openapi.yaml)
    public static class QuotaResponse {
        public long used;
        public long total;
    }
}
```

Gestion de session (token) :
```java
package com.votreecole.coffrefort.auth;

public class SessionManager {
    private static final SessionManager INSTANCE = new SessionManager();
    public static SessionManager getInstance() { return INSTANCE; }

    private String token;

    public void saveToken(String token) { this.token = token; }
    public String getToken() { return token; }
    public boolean isAuthenticated() { return token != null && !token.isBlank(); }
    public void clear() { token = null; }
}
```
Injectez le token dans `ApiClient` après login : `api.setBearerToken(session.getToken());`

---

## 6) Créer les écrans principaux (JavaFX)

Conformez-vous à `frontend.md` (Connexion, Dashboard/Quota, Explorateur, Partages, Détails fichier). Voici les bases :

- Utilisez FXML pour décrire l’interface, et une classe Controller pour la logique UI.
- Utilisez `TableView` pour lister les fichiers, `TreeView` pour les dossiers.
- Les appels réseau DOIVENT être faits hors du thread JavaFX (utilisez `Task`, `Service`, ou `new Thread`).
- Pour mettre à jour l’UI depuis un thread de fond, utilisez `Platform.runLater(...)`.

Exemple d’explorateur minimal :

`ExplorerView.fxml` :
```xml
<?xml version="1.0" encoding="UTF-8"?>
<?import javafx.scene.control.*?>
<?import javafx.scene.layout.*?>
<BorderPane xmlns:fx="http://javafx.com/fxml" fx:controller="com.votreecole.coffrefort.view.ExplorerController">
    <top>
        <ToolBar>
            <Button text="Nouveau dossier" onAction="#onNewFolder"/>
            <Button text="Téléverser" onAction="#onUpload"/>
            <TextField fx:id="searchField" promptText="Rechercher..."/>
        </ToolBar>
    </top>
    <left>
        <TreeView fx:id="treeFolders" />
    </left>
    <center>
        <TableView fx:id="tableItems">
            <columns>
                <TableColumn text="Nom" fx:id="colName" />
                <TableColumn text="Taille" fx:id="colSize" />
            </columns>
        </TableView>
    </center>
    <bottom>
        <Label fx:id="statusLabel" />
    </bottom>
</BorderPane>
```

`ExplorerController.java` :
```java
package com.votreecole.coffrefort.view;

import com.votreecole.coffrefort.api.ApiClient;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.scene.control.*;

public class ExplorerController {
    @FXML private TreeView<String> treeFolders;
    @FXML private TableView<FileItemVM> tableItems;
    @FXML private TableColumn<FileItemVM, String> colName;
    @FXML private TableColumn<FileItemVM, String> colSize;
    @FXML private Label statusLabel;

    private final ApiClient api = ApiClient.getInstance();

    @FXML
    public void initialize() {
        colName.setCellValueFactory(data -> data.getValue().nameProperty());
        colSize.setCellValueFactory(data -> data.getValue().sizeHumanProperty());
        loadRootFolder();
    }

    private void loadRootFolder() {
        statusLabel.setText("Chargement...");
        new Thread(() -> {
            try {
                // TODO: appeler l’API pour lister le dossier racine
                var items = java.util.List.of(
                        new FileItemVM("Documents", true, 0),
                        new FileItemVM("photo.jpg", false, 1_024_000)
                );
                Platform.runLater(() -> {
                    tableItems.getItems().setAll(items);
                    statusLabel.setText("Prêt");
                });
            } catch (Exception ex) {
                Platform.runLater(() -> statusLabel.setText("Erreur: " + ex.getMessage()));
            }
        }).start();
    }

    @FXML
    private void onNewFolder() {
        TextInputDialog d = new TextInputDialog("Nouveau dossier");
        d.setHeaderText("Nom du dossier");
        d.showAndWait().ifPresent(name -> {
            new Thread(() -> {
                try {
                    // TODO: appeler l’API de création de dossier
                    Platform.runLater(this::loadRootFolder);
                } catch (Exception ex) {
                    Platform.runLater(() -> statusLabel.setText("Erreur: " + ex.getMessage()));
                }
            }).start();
        });
    }

    @FXML
    private void onUpload() {
        FileChooser fc = new FileChooser();
        var file = fc.showOpenDialog(null);
        if (file == null) return;
        statusLabel.setText("Téléversement en cours...");
        new Thread(() -> {
            try {
                api.uploadFile("root", file);
                Platform.runLater(() -> {
                    statusLabel.setText("Fichier envoyé ✔");
                    loadRootFolder();
                });
            } catch (Exception ex) {
                Platform.runLater(() -> statusLabel.setText("Erreur: " + ex.getMessage()));
            }
        }).start();
    }

    // ViewModel simple pour l’exemple
    public static class FileItemVM {
        private final javafx.beans.property.SimpleStringProperty name = new javafx.beans.property.SimpleStringProperty();
        private final javafx.beans.property.SimpleStringProperty sizeHuman = new javafx.beans.property.SimpleStringProperty();
        private final boolean folder;
        private final long size;
        public FileItemVM(String name, boolean folder, long size) {
            this.name.set(name);
            this.folder = folder;
            this.size = size;
            this.sizeHuman.set(folder ? "—" : humanSize(size));
        }
        public javafx.beans.property.StringProperty nameProperty() { return name; }
        public javafx.beans.property.StringProperty sizeHumanProperty() { return sizeHuman; }
        private static String humanSize(long b) {
            String[] u = {"B","KB","MB","GB","TB"};
            int i = 0; double v = b;
            while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
            return String.format("%.1f %s", v, u[i]);
        }
    }
}
```

---

## 7) Se connecter à l’API — Conseils pratiques

- Lisez `openapi.yaml` pour connaître les endpoints exacts (URL, méthodes, schémas JSON, codes d’erreur).
- Centralisez la création des requêtes dans `ApiClient`.
- Ajoutez toujours l’en-tête `Authorization: Bearer <token>` pour les endpoints protégés.
- Gérez les erreurs HTTP (401 = non authentifié, 403 = interdit, 404 = introuvable, 409 = conflit, 500 = erreur serveur) et affichez un message clair à l’utilisateur (Label, Alert, Toast).

Exemple de téléchargement de fichier (OkHttp) :
```java
public void downloadFile(String fileId, java.nio.file.Path destination) throws IOException {
    Request req = new Request.Builder()
            .url(baseUrl + "/files/" + fileId + "/download")
            .header("Authorization", "Bearer " + bearerToken)
            .get()
            .build();
    try (Response resp = http.newCall(req).execute()) {
        if (!resp.isSuccessful()) throw httpError(resp);
        try (var in = resp.body().byteStream()) {
            java.nio.file.Files.copy(in, destination, java.nio.file.StandardCopyOption.REPLACE_EXISTING);
        }
    }
}
```

Upload avec barre de progression (concept) : créez un `RequestBody` qui wrappe le fichier et compte les octets écrits, puis mettez à jour une `ProgressBar` via `Platform.runLater`. Vous pouvez aussi afficher un `Dialog` avec progression et un bouton « Annuler ».

---

## 8) Fil d’exécution (threads) et réactivité

- Le thread JavaFX (UI) ne doit pas être bloqué par des appels réseau.
- Utilisez `javafx.concurrent.Task` pour structurer les opérations longues :
```java
Task<Void> task = new Task<>() {
    @Override protected Void call() throws Exception {
        // appel API long
        // updateProgress(bytesSent, totalBytes);
        return null;
    }
};
progressBar.progressProperty().bind(task.progressProperty());
new Thread(task).start();
```
- Pour mettre à jour l’interface depuis un thread de fond : `Platform.runLater(() -> label.setText("Fini"));`

---

## 9) Écrans à produire (récapitulatif de frontend.md)

1) Écran de connexion : champs (URL serveur, email, mot de passe/token), bouton « Se connecter », messages d’erreur.
2) Tableau de bord : carte « Quota » (utilisé/total, barre de progression), raccourcis.
3) Explorateur :
   - Panneau gauche TreeView (dossiers), panneau central TableView (fichiers/dossiers).
   - Actions : Nouveau dossier, Renommer, Déplacer, Supprimer, Téléverser, Télécharger, Remplacer (nouvelle version), Créer un lien de partage.
   - Recherche (local ou API si disponible).
4) Détails fichier : métadonnées, versions, actions (télécharger, remplacer, supprimer), liens de partage.
5) Gestion des partages : créer un lien (avec options : durée, usages max, mot de passe si supporté par l’API), lister mes liens, révoquer.

Pour chaque action, mappez un bouton/menu à un appel `ApiClient` correspondant aux endpoints de `openapi.yaml`.

---

## 10) Gestion des erreurs et UX

- Affichez les erreurs sous forme de bandeau/label rouge ou via `Alert` (type ERROR). Exemple :
```java
new Alert(Alert.AlertType.ERROR, "Impossible de se connecter (401). Vérifiez vos identifiants.").showAndWait();
```
- Faites des validations basiques côté client (nom de dossier non vide, taille de fichier raisonnable, etc.).
- Ajoutez des états de chargement (désactiver les boutons pendant un appel, afficher une ProgressIndicator).
- Logs de debug : `System.out.println` acceptables pour débuter, mais évitez d’afficher des tokens.

---

## 11) Formats utiles (utilitaires)

- Tailles humaines : fonction `humanSize(long)` comme dans l’exemple plus haut.
- Dates : utilisez `java.time.Instant` / `ZonedDateTime` et formatez avec `DateTimeFormatter`.
- Presse‑papiers :
```java
final javafx.scene.input.Clipboard clipboard = javafx.scene.input.Clipboard.getSystemClipboard();
final javafx.scene.input.ClipboardContent content = new javafx.scene.input.ClipboardContent();
content.putString(urlDePartage);
clipboard.setContent(content);
```

---

## 12) Sécurité basique côté client

- Ne stockez pas le mot de passe en clair. Conservez uniquement le token en mémoire (ou dans un fichier chiffré si demandé, mais ce n’est pas obligatoire pour le TP).
- À la déconnexion, effacez le token (`SessionManager.clear()`) et retournez à l’écran de connexion.

---

## 13) Emballage (packaging) et exécution

- Exécution pendant le dev : `mvn javafx:run` (Maven) ou `./gradlew runApp` (Gradle).
- Création d’un JAR exécutable :
  - Maven: ajoutez le plugin `maven-jar-plugin` avec `mainClass` (optionnel si plugin JavaFX déjà en place).
- Générer un installateur natif (avancé) : `jpackage` (nécessite JDK 17+). Exemple de base :
```
jpackage --name CoffreFort --input target --main-jar client-javafx-1.0.0.jar --main-class com.votreecole.coffrefort.App --type exe
```
Adaptez les chemins selon votre build.

---

## 14) Stratégie de développement par itérations

1. Écran Connexion qui obtient un token (factice si besoin au début), et navigation vers Explorateur.
2. Lister le contenu racine du stockage (GET). Afficher noms et tailles.
3. Téléverser un fichier (POST multipart) avec progression.
4. Télécharger un fichier (GET binaire).
5. Créer/Renommer/Déplacer/Supprimer des dossiers/fichiers.
6. Gérer les liens de partage (créer, lister, révoquer) et copier l’URL.
7. Détails d’un fichier : versions, remplacement par nouvelle version.
8. Tableau de bord : afficher le quota utilisé/total.

Testez chaque étape avec des données réelles puis passez à la suivante.

---

## 15) Dépannage (FAQ rapide)

- Erreur « module javafx.graphics not found » : assurez-vous d’utiliser Maven/Gradle avec dépendances JavaFX et lancez via le plugin (évitez d’exécuter directement le JAR sans modules JavaFX).
- Erreur 401/403 : token absent ou expiré. Reconnectez‑vous et renvoyez le token dans `Authorization`.
- UI figée : vous faites l’appel réseau sur le thread UI. Déplacez dans un `Task`/`Thread`.
- Chemins de ressources FXML introuvables : vérifiez que les fichiers `.fxml` sont dans `src/main/resources/com/votreecole/coffrefort/view/` et que `FXMLLoader.load(getResource("/com/votreecole/coffrefort/view/..."))` pointe bien dessus.
- CORS/URL : assurez-vous que l’URL du backend est correcte (http://localhost:8080 ou fournie par l’enseignant). Pour un client desktop, CORS ne s’applique pas (c’est côté navigateur), mais l’URL doit être atteignable.

---

## 16) À relier avec openapi.yaml

- Ouvrez `openapi.yaml` et identifiez :
  - les chemins (`paths`) pour fichiers/dossiers/partages ;
  - les schémas (`components.schemas`) pour construire vos DTO (classes Java Jackson) ;
  - les codes de réponse et messages d’erreur.
- Créez des méthodes `ApiClient` pour chaque besoin UI en vous basant sur ces définitions.

Bon courage ! Concentrez-vous sur la simplicité : un écran qui marche vaut mieux que dix écrans à moitié finis. Commencez petit, testez, puis enrichissez. 