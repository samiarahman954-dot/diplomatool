plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

// Where the app downloads the live server list from. CI overrides this with the branch it builds.
val serverListUrl = (findProperty("serverListUrl") as String?)
    ?: "https://raw.githubusercontent.com/samiarahman954-dot/diplomatool/main/vpn-android/server-list/servers.json"

android {
    namespace = "com.diplomatool.vpn"
    compileSdk = 35

    defaultConfig {
        applicationId = "com.diplomatool.vpn"
        minSdk = 24
        targetSdk = 35
        versionCode = 2
        versionName = "2.0"
        buildConfigField("String", "SERVER_LIST_URL", "\"$serverListUrl\"")
    }

    sourceSets {
        getByName("main") {
            // Bundle the server list so the app works before the first download.
            assets.srcDir("../server-list")
        }
    }

    compileOptions {
        isCoreLibraryDesugaringEnabled = true
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = "17"
    }

    buildFeatures {
        viewBinding = true
        buildConfig = true
    }
}

dependencies {
    implementation("com.wireguard.android:tunnel:1.0.20260102")

    implementation("androidx.core:core-ktx:1.15.0")
    implementation("androidx.core:core-splashscreen:1.0.1")
    implementation("androidx.appcompat:appcompat:1.7.0")
    implementation("androidx.activity:activity-ktx:1.9.3")
    implementation("androidx.lifecycle:lifecycle-runtime-ktx:2.8.7")
    implementation("androidx.recyclerview:recyclerview:1.3.2")
    implementation("androidx.swiperefreshlayout:swiperefreshlayout:1.1.0")
    implementation("com.google.android.material:material:1.12.0")
    implementation("com.journeyapps:zxing-android-embedded:4.3.0")
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.9.0")

    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.3")
}
