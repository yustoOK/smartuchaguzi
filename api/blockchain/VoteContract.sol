// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

import {ElectionContract} from "./ElectionContract.sol";

contract VoteContract {
    address public admin;
    ElectionContract public electionContract;

    struct Vote {
        address voter;
        uint positionId;
        uint candidateId;
        uint timestamp;
    }

    mapping(address => mapping(uint => bool)) public hasVoted; // voter => positionId => voted
    mapping(uint => mapping(uint => uint)) public voteCount; // positionId => candidateId => count
    Vote[] public votes;

    event VoteCast(address indexed voter, uint positionId, uint candidateId);

    modifier onlyDuringElection() {
        require(electionContract.isActive(), "Election not active");
        _;
    }

    constructor(address _electionContract) {
        admin = msg.sender;
        electionContract = ElectionContract(_electionContract);
    }

    function castVote(
        uint positionId,
        uint candidateId,
        uint collegeId,
        uint hostelId
    ) external onlyDuringElection {
        require(!hasVoted[msg.sender][positionId], "Already voted for this position");

        ElectionContract.Position memory pos = electionContract.getPosition(positionId);
        require(pos.collegeId == collegeId, "Voter not in college");
        if (pos.hostelId != 0) {
            require(pos.hostelId == hostelId, "Voter not in hostel");
        }

        hasVoted[msg.sender][positionId] = true;
        voteCount[positionId][candidateId]++;
        votes.push(Vote(msg.sender, positionId, candidateId, block.timestamp));
        emit VoteCast(msg.sender, positionId, candidateId);
    }

    function getVoteCount(uint positionId, uint candidateId) external view returns (uint) {
        return voteCount[positionId][candidateId];
    }

    function getPositionCandidates(uint positionId) external view returns (uint[] memory) {
        return electionContract.getPositionCandidates(positionId);
    }
}
