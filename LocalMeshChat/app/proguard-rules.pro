-keep class com.localmesh.chat.** { *; }
-keepclassmembers class * {
    public <init>(android.content.Context);
}
-dontwarn javax.annotation.**
