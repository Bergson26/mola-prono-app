package online.molaprono.app;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.media.AudioAttributes;
import android.media.RingtoneManager;
import android.net.Uri;
import android.os.Build;

import androidx.core.app.NotificationCompat;

import com.google.firebase.messaging.FirebaseMessagingService;
import com.google.firebase.messaging.RemoteMessage;

import java.util.Map;

public class MyFirebaseMessagingService extends FirebaseMessagingService {

    private static final String CHANNEL_ID = "mola_prono_channel";
    private static final int    NOTIF_ID   = 1001;

    @Override
    public void onMessageReceived(RemoteMessage remoteMessage) {
        Map<String, String> data = remoteMessage.getData();
        String title = data.containsKey("title") ? data.get("title") : "Mola Prono";
        String body  = data.containsKey("body")  ? data.get("body")  : "";

        // Sauvegarder pour transmission au JS via MainActivity
        getSharedPreferences("mola_notif", MODE_PRIVATE)
            .edit()
            .putString("pending_title", title)
            .putString("pending_body",  body)
            .apply();

        ensureChannel();

        Intent intent = new Intent(this, MainActivity.class);
        intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);
        PendingIntent pi = PendingIntent.getActivity(
            this, 0, intent,
            PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
        );

        Uri sound = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION);

        NotificationCompat.Builder builder =
            new NotificationCompat.Builder(this, CHANNEL_ID)
                .setSmallIcon(R.mipmap.ic_launcher)
                .setContentTitle(title)
                .setContentText(body)
                .setStyle(new NotificationCompat.BigTextStyle().bigText(body))
                .setPriority(NotificationCompat.PRIORITY_MAX)
                .setSound(sound)
                .setVibrate(new long[]{0, 500, 200, 500})
                .setOngoing(true)
                .setAutoCancel(false)
                .setContentIntent(pi);

        ((NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE))
            .notify(NOTIF_ID, builder.build());
    }

    private void ensureChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return;
        Uri sound = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION);
        AudioAttributes aa = new AudioAttributes.Builder()
            .setUsage(AudioAttributes.USAGE_NOTIFICATION)
            .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
            .build();
        NotificationChannel ch = new NotificationChannel(
            CHANNEL_ID, "Mola Prono", NotificationManager.IMPORTANCE_HIGH
        );
        ch.enableVibration(true);
        ch.setVibrationPattern(new long[]{0, 500, 200, 500});
        ch.setSound(sound, aa);
        ((NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE))
            .createNotificationChannel(ch);
    }
}
