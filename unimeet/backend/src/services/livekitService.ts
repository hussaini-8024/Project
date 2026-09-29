import { AccessToken, RoomConfiguration } from "livekit-server-sdk";
import { env } from "../config/env.js";
import type { AuthUser, ClassJoinGrant } from "../types.js";
import { ROOM_MAX_PARTICIPANTS } from "./capacityService.js";

export async function createLiveKitToken(
  user: AuthUser,
  roomName: string,
  grant: ClassJoinGrant,
) {
  const at = new AccessToken(env.LIVEKIT_API_KEY, env.LIVEKIT_API_SECRET, {
    identity: `${user.role}:${user.universityId}`,
    name: user.name,
    ttl: "4h",
    metadata: JSON.stringify({
      userId: user.id,
      role: user.role,
      universityId: user.universityId,
    }),
  });

  at.addGrant({
    room: roomName,
    roomJoin: true,
    roomCreate: true,
    canPublish: grant.canPublish,
    canSubscribe: true,
    canPublishData: true,
    roomAdmin: grant.roomAdmin,
    canUpdateOwnMetadata: true,
  });

  at.roomConfig = new RoomConfiguration({
    name: roomName,
    maxParticipants: ROOM_MAX_PARTICIPANTS,
    emptyTimeout: 300,
    departureTimeout: 20,
  });

  const token = await at.toJwt();
  return {
    token,
    url: env.LIVEKIT_URL,
    roomName,
  };
}
