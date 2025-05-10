// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

import "./ElectionContract.sol";

contract VoteContract {
    address public admin;
    ElectionContract public electionContract;

    struct Vote {
        uint256 electionId;
        address voter;
        uint256 positionId;
        uint256 candidateId;
        uint256 timestamp;
    }

    mapping(address => mapping(uint256 => mapping(uint256 => bool))) public hasVoted; // voter => electionId => positionId => voted
    mapping(uint256 => mapping(uint256 => uint256)) public voteCount; // positionId => candidateId => count
    Vote[] public votes;

    event VoteCast(uint256 electionId, address indexed voter, uint256 positionId, uint256 candidateId);

    modifier onlyDuringElection(uint256 electionId) {
        require(electionContract.getElection(electionId).isActive, "Election not active");
        _;
    }

    constructor(address _electionContract) {
        admin = msg.sender;
        electionContract = ElectionContract(_electionContract);
    }

    function castVote(
        uint256 electionId,
        uint256 positionId,
        uint256 candidateId,
        uint256 collegeId,
        uint256 hostelId
    ) external onlyDuringElection(electionId) {
        require(!hasVoted[msg.sender][electionId][positionId], "Already voted for this position in election");

        ElectionContract.Position memory pos = electionContract.getPosition(positionId);
        require(pos.electionId == electionId, "Position not in election");
        require(pos.collegeId == collegeId, "Voter not in college");
        if (pos.hostelId != 0) {
            require(pos.hostelId == hostelId, "Voter not in hostel");
        }

        bool isValidCandidate = false;
        uint256[] memory candidates = electionContract.getPositionCandidates(positionId);
        for (uint256 i = 0; i < candidates.length; i++) {
            if (candidates[i] == candidateId) {
                isValidCandidate = true;
                break;
            }
        }
        require(isValidCandidate, "Invalid candidate");

        hasVoted[msg.sender][electionId][positionId] = true;
        voteCount[positionId][candidateId]++;
        votes.push(Vote(electionId, msg.sender, positionId, candidateId, block.timestamp));
        emit VoteCast(electionId, msg.sender, positionId, candidateId);
    }

    function getVoteCount(uint256 positionId, uint256 candidateId) external view returns (uint256) {
        return voteCount[positionId][candidateId];
    }

    function getPositionCandidates(uint256 positionId) external view returns (uint256[] memory) {
        return electionContract.getPositionCandidates(positionId);
    }

    function getVotesByElection(uint256 electionId) external view returns (Vote[] memory) {
        uint256 count = 0;
        for (uint256 i = 0; i < votes.length; i++) {
            if (votes[i].electionId == electionId) {
                count++;
            } 
        }

        Vote[] memory result = new Vote[](count);
        uint256 index = 0;
        for (uint256 i = 0; i < votes.length; i++) {
            if (votes[i].electionId == electionId) {
                result[index] = votes[i];
                index++;
            }
        }
        return result;
    }
}