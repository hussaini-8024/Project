package com.localmesh.chat.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp
import androidx.compose.material3.Typography

private val Teal = Color(0xFF0F8F86)
private val TealDark = Color(0xFF0B6E68)
private val Navy = Color(0xFF0B1F2A)
private val Ink = Color(0xFF10232E)
private val Mist = Color(0xFFF3F6F7)
private val BubbleMine = Color(0xFF0F8F86)
private val BubbleTheirsLight = Color(0xFFE6EEF0)
private val BubbleTheirsDark = Color(0xFF1B333E)

private val LightColors = lightColorScheme(
    primary = Teal,
    onPrimary = Color.White,
    primaryContainer = Color(0xFFD4F3F0),
    onPrimaryContainer = Navy,
    secondary = Color(0xFF3D5A80),
    background = Mist,
    surface = Color.White,
    onBackground = Navy,
    onSurface = Navy,
    outline = Color(0xFFC5D0D4),
    error = Color(0xFFB3261E),
)

private val DarkColors = darkColorScheme(
    primary = Color(0xFF5EE0D6),
    onPrimary = Navy,
    primaryContainer = TealDark,
    onPrimaryContainer = Color.White,
    secondary = Color(0xFF9BB4D0),
    background = Navy,
    surface = Ink,
    onBackground = Color(0xFFE8F1F3),
    onSurface = Color(0xFFE8F1F3),
    outline = Color(0xFF35505A),
    error = Color(0xFFFFB4AB),
)

val LocalMeshTypography = Typography(
    headlineLarge = TextStyle(fontWeight = FontWeight.Bold, fontSize = 28.sp, fontFamily = FontFamily.SansSerif),
    headlineMedium = TextStyle(fontWeight = FontWeight.SemiBold, fontSize = 22.sp),
    titleLarge = TextStyle(fontWeight = FontWeight.SemiBold, fontSize = 20.sp),
    titleMedium = TextStyle(fontWeight = FontWeight.Medium, fontSize = 16.sp),
    bodyLarge = TextStyle(fontSize = 16.sp, lineHeight = 22.sp),
    bodyMedium = TextStyle(fontSize = 14.sp, lineHeight = 20.sp),
    labelLarge = TextStyle(fontWeight = FontWeight.Medium, fontSize = 14.sp),
)

val MeshBubbleMine: Color @Composable get() = BubbleMine
val MeshBubbleTheirs: Color @Composable get() =
    if (isSystemInDarkTheme()) BubbleTheirsDark else BubbleTheirsLight

@Composable
fun LocalMeshTheme(content: @Composable () -> Unit) {
    val dark = isSystemInDarkTheme()
    MaterialTheme(
        colorScheme = if (dark) DarkColors else LightColors,
        typography = LocalMeshTypography,
        content = content,
    )
}
