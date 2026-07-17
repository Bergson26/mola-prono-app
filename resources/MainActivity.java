package online.molaprono.app;

import android.app.NotificationManager;
import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Bundle;

import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
    }

    @Override
    protected void onResume() {
        super.onResume();
        handlePendingNotification();
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        handlePendingNotification();
    }

    private void handlePendingNotification() {
        // Annuler la notification système (elle a été lue)
        NotificationManager nm = (NotificationManager) getSystemService(NOTIFICATION_SERVICE);
        if (nm != null) nm.cancelAll();

        // Lire et effacer la notification en attente
        SharedPreferences prefs = getSharedPreferences("mola_notif", MODE_PRIVATE);
        String title = prefs.getString("pending_title", null);
        if (title == null) return;

        String body = prefs.getString("pending_body", "");
        prefs.edit().remove("pending_title").remove("pending_body").apply();

        // Transmettre au JS pour affichage du popup in-app
        final String fTitle = title;
        final String fBody  = body;
        getBridge().getWebView().post(() ->
            getBridge().eval(
                "window.onNativeNotifTap && window.onNativeNotifTap('" +
                js(fTitle) + "','" + js(fBody) + "')",
                null
            )
        );
    }

    private static String js(String s) {
        if (s == null) return "";
        return s.replace("\\", "\\\\")
                .replace("'",  "\\'")
                .replace("\n", "\\n")
                .replace("\r", "");
    }
}
